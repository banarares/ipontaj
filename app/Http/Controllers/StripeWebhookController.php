<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\LegalAcceptance;
use App\Models\User;
use App\Notifications\LegalContractConfirmed;
use App\Services\ActivityFeed;
use App\Services\BillingConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends WebhookController
{
    public function handleWebhook(Request $request): Response
    {
        abort_unless(config('cashier.webhook.secret'), 503);
        $payload = $request->json()->all();
        abort_unless(is_string($payload['id'] ?? null) && is_string($payload['type'] ?? null), 400);
        abort_unless(is_bool($payload['livemode'] ?? null) && $payload['livemode'] === ! app(BillingConfiguration::class)->testMode(), 400);

        return DB::transaction(function () use ($request, $payload) {
            // A unique insert serializes retries before Cashier changes any subscriptions.
            if (! DB::table('webhook_events')->insertOrIgnore(['id' => $payload['id'], 'processed_at' => now()])) {
                return response('Already processed', 200);
            }
            $response = parent::handleWebhook($request);
            if ($payload['type'] === 'checkout.session.completed') {
                $session = $payload['data']['object'] ?? [];
                $acceptanceId = $session['metadata']['legal_acceptance_id'] ?? null;
                if (is_string($acceptanceId) && ctype_digit($acceptanceId) && ($session['payment_status'] ?? null) === 'paid') {
                    $acceptance = LegalAcceptance::where('purpose', 'checkout')->whereNull('confirmed_at')->lockForUpdate()->find($acceptanceId);
                    $company = $acceptance?->company_id ? Company::find($acceptance->company_id) : null;
                    if ($acceptance && $company && $company->stripe_id !== null && $company->stripe_id === ($session['customer'] ?? null) && ($acceptance->facts['checkout_session_id'] ?? null) === ($session['id'] ?? null)) {
                        $acceptance->update(['confirmed_at' => now()]);
                        app(ActivityFeed::class)->record($company, $acceptance->user_id, 'Abonamentul a fost confirmat', '/billing', true);
                        User::find($acceptance->user_id)?->notify(new LegalContractConfirmed($acceptance->id));
                    }
                }
            }

            if (in_array($payload['type'], ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted', 'invoice.payment_failed'])) {
                $company = Company::where('stripe_id', $payload['data']['object']['customer'] ?? null)->first();
                if ($company) {
                    app(ActivityFeed::class)->record($company, null, $payload['type'] === 'invoice.payment_failed' ? 'Plata abonamentului necesită atenție' : 'Abonamentul a fost actualizat', '/billing', true);
                }
            }

            return $response;
        }, 3);
    }
}
