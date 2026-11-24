<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Cashier\Subscription;
use Stripe\Invoice;
use Stripe\StripeClient;

class VerifyStripeTestCommand extends Command
{
    protected $signature = 'pontaj:stripe:verify {--webhook-url=http://127.0.0.1:8000/stripe/webhook}';

    protected $description = 'Verifică în Stripe test checkoutul, portalul, plata, webhookurile și anularea, apoi șterge datele fictive';

    public function handle(): int
    {
        $key = config('cashier.secret');
        $url = $this->option('webhook-url');
        if (! is_string($key) || ! str_starts_with($key, 'sk_test_') || ! filled(config('cashier.webhook.secret')) || ! filled(config('ipontaj.stripe_team_price'))) {
            $this->error('Configurează mai întâi cheile de test, prețul și listenerul webhook.');

            return self::FAILURE;
        }
        if (! is_string($url) || ! in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1', '[::1]'], true)) {
            $this->error('Webhookul trebuie să fie local.');

            return self::FAILURE;
        }
        $stripe = new StripeClient($key);
        $company = null;
        $subscription = null;
        $checkout = null;
        $customer = null;
        $canceled = false;
        $report = ['verified_at' => now()->toIso8601String(), 'mode' => 'test'];
        try {
            $company = Company::create(['name' => 'Verificare Stripe iPontaj — firmă fictivă']);
            $customer = $stripe->customers->create(['name' => $company->name, 'email' => 'stripe-verification@example.test', 'metadata' => ['application' => 'ipontaj', 'purpose' => 'automated-test']]);
            $company->update(['stripe_id' => $customer->id]);
            $checkout = $company->newSubscription('default', config('ipontaj.stripe_team_price'))->checkout(['success_url' => url('/billing?checkout=returned'), 'cancel_url' => url('/billing')])->asStripeCheckoutSession();
            if ($checkout->livemode || $checkout->amount_total !== 4900 || $checkout->currency !== 'ron') {
                throw new \RuntimeException('Checkoutul nu corespunde prețului.');
            }
            $report['checkout'] = true;
            $this->info('Checkout Stripe de test creat: total 49 lei, RON.');
            $portal = $company->billingPortalUrl(url('/billing'), array_filter(['configuration' => config('ipontaj.stripe_portal_configuration')]));
            if (! str_starts_with($portal, 'https://billing.stripe.com/')) {
                throw new \RuntimeException('Portal invalid.');
            }
            $report['portal'] = true;
            $this->info('Sesiunea portalului Stripe a fost creată.');
            $method = $stripe->paymentMethods->attach('pm_card_visa', ['customer' => $customer->id]);
            $subscription = $stripe->subscriptions->create(['customer' => $customer->id, 'items' => [['price' => config('ipontaj.stripe_team_price')]], 'default_payment_method' => $method->id, 'payment_behavior' => 'error_if_incomplete', 'expand' => ['latest_invoice']]);
            $invoice = $subscription->latest_invoice;
            if (! $invoice instanceof Invoice || $subscription->livemode || $subscription->status !== 'active' || $invoice->status !== 'paid' || $invoice->total !== 4900) {
                throw new \RuntimeException('Plata de test nu este confirmată.');
            }
            $report['payment'] = true;
            $this->info('Plată de test confirmată în Stripe: 49 lei.');
            if (! $this->waitFor(fn () => $company->subscriptions()->where('stripe_id', $subscription->id)->where('stripe_status', 'active')->exists())) {
                throw new \RuntimeException('Webhookul de activare nu a ajuns la aplicație.');
            }
            $report['activation_webhook'] = true;
            $this->info('Webhookul a activat abonamentul firmei în iPontaj.');
            $event = null;
            foreach ($stripe->events->all(['type' => 'customer.subscription.created', 'limit' => 20])->data as $candidate) {
                if ($candidate->data->object->id === $subscription->id) {
                    $event = $candidate;
                    break;
                }
            }
            if (! $event) {
                throw new \RuntimeException('Evenimentul Stripe de test nu a fost găsit.');
            }
            $payload = $event->toJSON();
            $timestamp = time();
            $signature = hash_hmac('sha256', $timestamp.'.'.$payload, config('cashier.webhook.secret'));
            $before = DB::table('webhook_events')->where('id', $event->id)->count();
            $response = Http::timeout(15)->withHeaders(['Stripe-Signature' => "t=$timestamp,v1=$signature"])->withBody($payload, 'application/json')->post($url);
            if (! $response->successful() || $before !== 1 || DB::table('webhook_events')->where('id', $event->id)->count() !== 1 || $company->subscriptions()->count() !== 1) {
                throw new \RuntimeException('Evenimentul duplicat nu a fost tratat corect.');
            }
            $report['duplicate_webhook'] = true;
            $this->info('Retrimiterea evenimentului verificată: un singur abonament și un singur eveniment procesat.');
            $stripe->subscriptions->cancel($subscription->id);
            $canceled = true;
            if (! $this->waitFor(fn () => ! $company->fresh()->subscribed('default'))) {
                throw new \RuntimeException('Webhookul de anulare nu a ajuns la aplicație.');
            }
            $report['cancellation_webhook'] = true;
            $report['amount'] = 4900;
            $report['currency'] = 'ron';
            $report['price'] = config('ipontaj.stripe_team_price');
            $this->info('Anularea și revenirea la planul de bază sunt confirmate.');
            file_put_contents(base_path('.runtime/stripe-verification.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('Verificarea nu a fost finalizată ('.class_basename($error).'). Ultimul pas reușit este afișat mai sus.');

            return self::FAILURE;
        } finally {
            if ($subscription && ! $canceled) {
                try {
                    $stripe->subscriptions->cancel($subscription->id);
                } catch (\Throwable) {
                }
            }
            if ($checkout) {
                try {
                    $stripe->checkout->sessions->expire($checkout->id);
                } catch (\Throwable) {
                }
            }
            if ($customer) {
                try {
                    $stripe->customers->delete($customer->id);
                } catch (\Throwable) {
                    $this->warn('Clientul fictiv trebuie șters manual: '.$customer->id);
                }
            }
            if ($company) {
                foreach (Subscription::where('company_id', $company->id)->get() as $local) {
                    $local->items()->delete();
                    $local->delete();
                }$company->delete();
            }
            $this->info('Datele firmei fictive folosite la verificare au fost eliminate.');
        }
    }

    private function waitFor(\Closure $condition): bool
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            if ($condition()) {
                return true;
            }usleep(500000);
        }

        return false;
    }
}
