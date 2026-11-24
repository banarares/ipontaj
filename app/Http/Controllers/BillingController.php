<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyContext;
use App\Services\LegalAgreements;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    private function company(Request $request, CompanyContext $context): Company
    {
        $company = $context->owner($request);
        abort_if($company->is_demo, 403);
        abort_unless(config('ipontaj.billing_enabled') && config('cashier.secret') && config('ipontaj.stripe_team_price'), 503, 'Plata nu este configurată.');
        abort_unless(str_starts_with(config('cashier.secret'), 'sk_test_'), 503, 'Abonamentele nu sunt disponibile momentan. Contact: office@ipontaj.ro.');

        return $company;
    }

    public function checkout(Request $request, CompanyContext $context): Response
    {
        $company = $this->company($request, $context);
        abort_if($company->subscribed('default'), 422, 'Ai deja un abonament activ. Deschide portalul de abonament.');
        $data = $request->validate(['terms_accepted' => 'accepted', 'legal_version' => ['required', Rule::in([config('legal.version')])], 'service_start_requested' => $company->customer_type === 'consumer' ? 'accepted' : 'nullable|boolean']);
        $acceptance = app(LegalAgreements::class)->record($request->user(), 'checkout', ['termeni-si-conditii', 'abonamente-si-anulare', 'retragere'], $company);
        $acceptance->update(['facts' => [...$acceptance->facts, 'customer_type' => $company->customer_type, 'service_start_requested' => $request->boolean('service_start_requested'), 'test_mode' => true]]);

        $checkout = $company->newSubscription('default', config('ipontaj.stripe_team_price'))->checkout(['success_url' => url('/billing?checkout=returned'), 'cancel_url' => url('/billing'), 'billing_address_collection' => 'required', 'automatic_tax' => ['enabled' => false], 'tax_id_collection' => ['enabled' => $company->customer_type === 'business'], 'metadata' => ['legal_acceptance_id' => (string) $acceptance->id], 'custom_text' => ['submit' => ['message' => '49 lei/lună, preț final. Reînnoire lunară până la anulare. Operator neînregistrat în scopuri de TVA. Condiții și retragere: https://ipontaj.ro/abonamente-si-anulare.']]]);
        $acceptance->update(['facts' => [...$acceptance->facts, 'checkout_session_id' => $checkout->asStripeCheckoutSession()->id]]);

        return Inertia::location($checkout->asStripeCheckoutSession()->url);
    }

    public function portal(Request $request, CompanyContext $context): Response
    {
        $company = $this->company($request, $context);
        abort_unless($company->stripe_id !== null, 422);

        return Inertia::location($company->billingPortalUrl(url('/billing'), array_filter(['configuration' => config('ipontaj.stripe_portal_configuration')])));
    }
}
