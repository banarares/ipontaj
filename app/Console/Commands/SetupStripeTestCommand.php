<?php

namespace App\Console\Commands;

use App\Support\EnvironmentFile;
use Illuminate\Console\Command;
use Stripe\StripeClient;

class SetupStripeTestCommand extends Command
{
    protected $signature = 'pontaj:stripe:setup {--no-write : Verifică și creează resursele de test fără modificarea .env}';

    protected $description = 'Pregătește produsul, prețul și portalul Stripe numai în modul de test';

    public function handle(): int
    {
        $secret = config('cashier.secret');
        if (! is_string($secret) || (! str_starts_with($secret, 'sk_test_') && ! str_starts_with($secret, 'rk_test_'))) {
            $this->error('Este necesară o cheie secretă Stripe de test. Cheile live sunt refuzate.');

            return self::FAILURE;
        }
        $stripe = new StripeClient($secret);
        try {
            $lookup = 'ipontaj_team_monthly_ron_4900_inclusive_v1';
            $price = $stripe->prices->all(['lookup_keys' => [$lookup], 'active' => true, 'limit' => 1])->data[0] ?? null;
            if (! $price) {
                $product = $stripe->products->create(['name' => 'iPontaj Echipă', 'description' => 'Până la 15 persoane active în pontaj', 'metadata' => ['application' => 'ipontaj', 'plan' => 'team']], ['idempotency_key' => 'ipontaj-team-product-v1']);
                $price = $stripe->prices->create(['product' => $product->id, 'unit_amount' => 4900, 'currency' => 'ron', 'recurring' => ['interval' => 'month'], 'tax_behavior' => 'inclusive', 'lookup_key' => $lookup, 'metadata' => ['application' => 'ipontaj', 'plan' => 'team']], ['idempotency_key' => $lookup]);
            }
            if ($price->livemode || $price->unit_amount !== 4900 || $price->currency !== 'ron' || $price->recurring->interval !== 'month' || $price->recurring->interval_count !== 1 || $price->tax_behavior !== 'inclusive') {
                $this->error('Prețul Stripe nu corespunde planului: 49 lei/lună, preț final, în test.');

                return self::FAILURE;
            }
            $portal = null;
            foreach ($stripe->billingPortal->configurations->all(['active' => true, 'limit' => 100])->data as $configuration) {
                if (($configuration->metadata->application ?? null) === 'ipontaj') {
                    $portal = $configuration;
                    break;
                }
            }
            if (! $portal) {
                $portal = $stripe->billingPortal->configurations->create(['business_profile' => ['headline' => 'Gestionează abonamentul iPontaj'], 'features' => ['customer_update' => ['enabled' => true, 'allowed_updates' => ['name', 'email', 'address']], 'invoice_history' => ['enabled' => true], 'payment_method_update' => ['enabled' => true], 'subscription_cancel' => ['enabled' => true, 'mode' => 'at_period_end']], 'metadata' => ['application' => 'ipontaj']], ['idempotency_key' => 'ipontaj-portal-test-v1']);
            }
            if (! $this->option('no-write')) {
                EnvironmentFile::update(['STRIPE_TEAM_PRICE' => $price->id, 'STRIPE_PORTAL_CONFIGURATION' => $portal->id, 'BILLING_ENABLED' => 'true']);
                $this->callSilently('config:clear');
            }
            $this->info('Preț de test verificat: 49 lei/lună, RON, preț final.');
            $this->line('Preț: '.$price->id);
            $this->line('Portal: '.$portal->id);
            $this->info('Continuă cu pontaj:stripe:listen pentru webhookurile locale.');

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('Configurarea Stripe nu a reușit ('.class_basename($error).'). Verifică permisiunile cheii de test și conexiunea.');

            return self::FAILURE;
        }
    }
}
