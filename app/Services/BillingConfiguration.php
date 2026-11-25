<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

class BillingConfiguration
{
    public function testMode(): bool
    {
        return str_starts_with((string) config('cashier.secret'), 'sk_test_');
    }

    public function ready(): bool
    {
        $secret = (string) config('cashier.secret');
        $mode = $this->testMode() ? 'test' : 'live';

        return (bool) config('ipontaj.billing_enabled')
            && (str_starts_with($secret, 'sk_test_') || str_starts_with($secret, 'sk_live_'))
            && str_starts_with((string) config('cashier.key'), 'pk_'.$mode.'_')
            && str_starts_with((string) config('ipontaj.stripe_team_price'), 'price_')
            && str_starts_with((string) config('cashier.webhook.secret'), 'whsec_')
            && config('cashier.currency') === 'ron'
            && (! app()->isProduction() || str_starts_with((string) config('app.url'), 'https://'));
    }

    public function ensurePrice(): void
    {
        $key = 'billing:price:'.hash('sha256', config('cashier.secret').'|'.config('ipontaj.stripe_team_price'));
        try {
            $valid = Cache::remember($key, 300, function () {
                $price = Cashier::stripe()->prices->retrieve(config('ipontaj.stripe_team_price'));

                return $price->active === true && $price->livemode === ! $this->testMode()
                    && $price->currency === 'ron' && $price->unit_amount === 4900
                    && $price->type === 'recurring' && $price->recurring->interval === 'month'
                    && $price->recurring->interval_count === 1;
            });
        } catch (\Throwable $error) {
            Log::warning('Stripe price verification failed.', ['error_type' => class_basename($error)]);
            abort(503, 'Nu putem verifica abonamentul acum. Reîncearcă mai târziu.');
        }
        abort_unless($valid, 503, 'Abonamentul nu este configurat corect. Contact: office@ipontaj.ro.');
    }
}
