<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\LegalAcceptance;
use App\Services\AnalyticsEvents;
use App\Services\BillingConfiguration;
use App\Services\CompanyContext;
use App\Services\LegalAgreements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    private function company(Request $request, CompanyContext $context): Company
    {
        $company = $context->owner($request);
        abort_if($company->is_demo, 403);
        abort_unless(app(BillingConfiguration::class)->ready(), 503, 'Abonamentele nu sunt disponibile momentan. Contact: office@ipontaj.ro.');

        return $company;
    }

    public function checkout(Request $request, CompanyContext $context): Response
    {
        $company = $this->company($request, $context);
        abort_if($company->subscribed('default'), 422, 'Ai deja un abonament activ. Deschide portalul de abonament.');
        $data = $request->validate(['terms_accepted' => 'accepted', 'legal_version' => ['required', Rule::in([config('legal.version')])], 'service_start_requested' => $company->customer_type === 'consumer' ? 'accepted' : 'nullable|boolean']);
        app(BillingConfiguration::class)->ensurePrice();
        $acceptance = app(LegalAgreements::class)->record($request->user(), 'checkout', ['termeni-si-conditii', 'abonamente-si-anulare', 'retragere'], $company);
        $acceptance->update(['facts' => [...$acceptance->facts, 'customer_type' => $company->customer_type, 'service_start_requested' => $request->boolean('service_start_requested'), 'test_mode' => app(BillingConfiguration::class)->testMode()]]);

        $checkout = $company->newSubscription('default', config('ipontaj.stripe_team_price'))->checkout(['success_url' => url('/billing').'?checkout=returned&session_id={CHECKOUT_SESSION_ID}', 'cancel_url' => url('/billing'), 'billing_address_collection' => 'required', 'customer_update' => ['address' => 'auto'], 'automatic_tax' => ['enabled' => false], 'tax_id_collection' => ['enabled' => $company->customer_type === 'business'], 'metadata' => ['legal_acceptance_id' => (string) $acceptance->id], 'custom_text' => ['submit' => ['message' => '49 lei/lună, preț final. Reînnoire lunară până la anulare. Operator neînregistrat în scopuri de TVA. Condiții și retragere: https://ipontaj.ro/abonamente-si-anulare.']]]);
        $acceptance->update(['facts' => [...$acceptance->facts, 'checkout_session_id' => $checkout->asStripeCheckoutSession()->id]]);

        return Inertia::location($checkout->asStripeCheckoutSession()->url);
    }

    public function show(Request $request, WorkspaceController $workspace): InertiaResponse|RedirectResponse
    {
        $sessionId = $request->query('session_id');
        if ($sessionId === null) {
            return $workspace->index($request, 'billing');
        }
        // Stripe returns here only after a completed checkout; the session must be one this user started.
        $acceptance = is_string($sessionId) && preg_match('/^cs_(test|live)_[A-Za-z0-9]{1,200}$/', $sessionId)
            ? LegalAcceptance::where('user_id', $request->user()->id)->where('purpose', 'checkout')->where('facts->checkout_session_id', $sessionId)->first()
            : null;
        if ($acceptance && empty($acceptance->facts['analytics_purchase_at'])) {
            $acceptance->update(['facts' => [...$acceptance->facts, 'analytics_purchase_at' => now()->toIso8601String()]]);
            app(AnalyticsEvents::class)->queue($request, 'purchase', ecommerce: AnalyticsEvents::teamPlan('T-'.substr(hash('sha256', $sessionId), 0, 20)));
        }

        return redirect('/billing?checkout=returned');
    }

    public function portal(Request $request, CompanyContext $context): Response
    {
        $company = $this->company($request, $context);
        abort_unless($company->stripe_id !== null, 422);

        return Inertia::location($company->billingPortalUrl(url('/billing'), array_filter(['configuration' => config('ipontaj.stripe_portal_configuration')])));
    }
}
