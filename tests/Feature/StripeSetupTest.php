<?php

namespace Tests\Feature;

use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Tests\TestCase;

class StripeSetupTest extends TestCase
{
    public function test_live_setup_is_refused_before_any_api_request(): void
    {
        config(['cashier.secret' => 'sk_live_fixture']);
        $this->artisan('pontaj:stripe:setup', ['--no-write' => true])->expectsOutputToContain('Cheile live sunt refuzate')->assertFailed();
    }

    public function test_repeated_test_setup_reuses_the_product_price_and_portal(): void
    {
        config(['cashier.secret' => 'sk_test_fixture']);
        $client = new class implements ClientInterface
        {
            public array $calls = [];

            public bool $created = false;

            public bool $portal = false;

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
            {
                $path = parse_url($absUrl, PHP_URL_PATH);
                $this->calls[] = [$method, $path, $params];
                $price = ['id' => 'price_fixture', 'object' => 'price', 'livemode' => false, 'unit_amount' => 4900, 'currency' => 'ron', 'tax_behavior' => 'inclusive', 'recurring' => ['interval' => 'month', 'interval_count' => 1]];
                $portal = ['id' => 'bpc_fixture', 'object' => 'billing_portal.configuration', 'metadata' => ['application' => 'ipontaj']];
                if ($path === '/v1/products') {
                    $result = ['id' => 'prod_fixture', 'object' => 'product'];
                } elseif ($path === '/v1/prices' && $method === 'post') {
                    $this->created = true;
                    $result = $price;
                } elseif ($path === '/v1/prices') {
                    $result = ['object' => 'list', 'data' => $this->created ? [$price] : []];
                } elseif ($method === 'post') {
                    $this->portal = true;
                    $result = $portal;
                } else {
                    $result = ['object' => 'list', 'data' => $this->portal ? [$portal] : []];
                }

                return [json_encode($result), 200, []];
            }
        };
        ApiRequestor::setHttpClient($client);
        try {
            for ($i = 0; $i < 2; $i++) {
                $this->artisan('pontaj:stripe:setup', ['--no-write' => true])->assertSuccessful();
            }
            $creates = array_values(array_filter($client->calls, fn ($call) => $call[0] === 'post'));
            $this->assertCount(3, $creates);
            $this->assertSame(4900, $creates[1][2]['unit_amount']);
            $this->assertSame('inclusive', $creates[1][2]['tax_behavior']);
            $this->assertSame('at_period_end', $creates[2][2]['features']['subscription_cancel']['mode']);
        } finally {
            ApiRequestor::setHttpClient(CurlClient::instance());
        }
    }
}
