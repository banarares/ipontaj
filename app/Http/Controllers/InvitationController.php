<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Invitation;
use App\Notifications\TeamInvitation;
use App\Services\CompanyContext;
use App\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function managed(Request $request, CompanyContext $context, TeamService $service): RedirectResponse
    {
        $company = $context->owner($request);
        abort_if($company->is_demo, 403, 'Adăugarea colegilor este disponibilă după crearea unui cont.');
        $data = $request->validate([
            'first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100',
            'responsibility_confirmed' => 'accepted',
            'joined_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:'.now($company->timezone)->toDateString()],
        ]);
        $service->addManaged($company, $data, $request->user()->id);

        return back()->with('success', 'Colegul a fost adăugat fără cont. Îi poți înregistra orele din Pontaj lunar.');
    }

    public function store(Request $request, CompanyContext $context, TeamService $service): RedirectResponse
    {
        $company = $context->owner($request);
        abort_if($company->is_demo, 403, 'Invitațiile sunt disponibile după crearea unui cont.');
        $data = $request->validate(['first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100', 'email' => 'required|email|max:254', 'send_email' => 'boolean']);
        [$invitation,$token] = $service->invite($company, $data);
        $url = url('/invite/'.$token);
        if ($request->boolean('send_email')) {
            Notification::route('mail', $invitation->email)->notify(new TeamInvitation($invitation->id, $url));
        }

        return back()->with('invitation_url', $url)->with('success', 'Invitația este pregătită. Linkul expiră în 7 zile.');
    }

    public function revoke(Request $request, Invitation $invitation, CompanyContext $context): RedirectResponse
    {
        $company = $context->owner($request);
        abort_unless($invitation->company_id === $company->id, 404);
        DB::transaction(function () use ($company, $invitation) {
            Company::lockForUpdate()->findOrFail($company->id);
            $invitation->refresh();
            abort_if($invitation->accepted_at !== null, 422);
            $invitation->update(['revoked_at' => now()]);
        }, 3);

        return back()->with('success', 'Invitația a fost revocată.');
    }

    public function show(Request $request, string $token): Response
    {
        $invite = Invitation::with('company')->where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_if($invite->revoked_at || $invite->expires_at->isPast(), 410, 'Invitația a expirat sau a fost revocată.');
        if (! $request->user()) {
            $request->session()->put('url.intended', url('/invite/'.$token));
        }

        return Inertia::render('Invitation', ['invitation' => ['name' => $invite->name, 'first_name' => $invite->first_name, 'last_name' => $invite->last_name, 'email' => $invite->email, 'company' => $invite->company->name, 'accepted' => (bool) $invite->accepted_at], 'token' => $token]);
    }

    public function accept(Request $request, string $token, TeamService $service): RedirectResponse
    {
        $member = $service->accept($token, $request->user());
        $request->session()->put('company_id', $member->company_id);

        return redirect('/today')->with('success', 'Bine ai venit în echipă!');
    }
}
