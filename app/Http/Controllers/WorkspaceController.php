<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\Membership;
use App\Models\MonthClosure;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\CompanyContext;
use App\Services\CompanyIdentity;
use App\Services\ExcelService;
use App\Services\LegalAgreements;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WorkspaceController extends Controller
{
    public function __construct(private CompanyContext $context, private AttendanceService $attendance) {}

    public function index(Request $request, string $section = 'overview'): Response|RedirectResponse
    {
        if (! $request->user()->memberships()->where('active', true)->exists()) {
            return redirect('/onboarding');
        }
        $member = $this->context->membership($request)->load('user');
        $company = $member->company;
        $owner = $member->role === 'owner';
        if (! $owner && in_array($section, ['team', 'settings', 'billing', 'month'])) {
            abort(403);
        }
        if (! $owner && $section === 'overview') {
            return redirect('/today');
        }
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'date' => ['nullable', 'date_format:Y-m-d']]);
        $today = now($company->timezone)->toDateString();
        $date = $request->filled('date') ? $request->string('date')->toString() : null;
        $month = $date ? substr($date, 0, 7) : ($request->input('month') ?: substr($today, 0, 7));
        $date ??= $month === substr($today, 0, 7) ? $today : $month.'-01';
        $billingTestMode = config('ipontaj.billing_enabled') && str_starts_with((string) config('cashier.secret'), 'sk_test_');
        $summary = $owner ? $this->attendance->summary($company, $month) : null;
        $members = $owner ? $company->memberships()->with('user')->orderBy('id')->get() : collect([$member->load('user')]);
        $entries = $company->entries()->with('intervals')->whereDate('date', '>=', $month.'-01')->whereDate('date', '<=', Carbon::parse($month.'-01')->endOfMonth()->toDateString())->when(! $owner, fn ($q) => $q->where('membership_id', $member->id))->get();

        return Inertia::render('Workspace', [
            'section' => $section, 'company' => $company, 'membership' => $member, 'companies' => $request->user()->memberships()->with('company')->where('active', true)->get()->map(fn ($m) => ['id' => $m->company_id, 'name' => $m->company->name]),
            'members' => $members, 'month' => $month, 'today' => $today, 'selectedDate' => $date, 'selectedDateActive' => $member->activeOn($date), 'summary' => $summary, 'entries' => $entries, 'schedule' => $this->attendance->schedule($member, $date),
            'schedules' => $owner ? $company->schedules()->orderByDesc('effective_from')->get() : [],
            'invitations' => $owner ? $company->invitations()->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->get() : [],
            'closure' => MonthClosure::where('company_id', $company->id)->where('month', $month)->first(),
            'audit' => $owner ? AuditEvent::with('user:id,first_name,last_name,name')->where('company_id', $company->id)->latest()->limit(20)->get() : [],
            'seatLimit' => $company->seatLimit(), 'occupiedSeats' => $company->occupiedSeats(), 'billingEnabled' => config('ipontaj.billing_enabled'), 'billingReady' => $billingTestMode && filled(config('ipontaj.stripe_team_price')), 'billingTestMode' => $billingTestMode, 'subscribed' => $company->subscribed('default'), 'absences' => AttendanceService::ABSENCES,
        ]);
    }

    public function onboarding(Request $request): Response
    {
        abort_if($request->user()->is_demo, 403);

        return Inertia::render('Onboarding');
    }

    public function createCompany(Request $request): RedirectResponse
    {
        abort_if($request->user()->is_demo, 403);
        $consumer = $request->input('customer_type', 'business') === 'consumer';
        $data = $request->validate(['customer_type' => 'sometimes|in:business,consumer', 'cif' => [$consumer ? 'nullable' : 'required', 'string', 'max:20'], 'name' => 'required|string|max:255', 'timezone' => 'required|timezone', 'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i', 'include_owner' => 'required|boolean', 'schedule_confirmed' => 'accepted', 'dpa_accepted' => $consumer ? 'nullable|boolean' : 'accepted', 'legal_version' => ['required', Rule::in([config('legal.version')])]]);
        $identity = app(CompanyIdentity::class)->attributes($consumer ? null : $data['cif'], $data['name']);
        $company = DB::transaction(function () use ($request, $data, $identity) {
            // Serialize firm creation for the same owner, including simultaneous requests.
            User::lockForUpdate()->findOrFail($request->user()->id);
            if (config('ipontaj.billing_enabled') && $request->user()->memberships()->where('role', 'owner')->exists()) {
                throw ValidationException::withMessages(['name' => 'Planul inițial permite o firmă.']);
            }
            $company = Company::create([...$identity, 'customer_type' => $data['customer_type'] ?? 'business', 'timezone' => $data['timezone']]);
            $company->memberships()->create(['user_id' => $request->user()->id, 'role' => 'owner', 'tracked' => $data['include_owner'], 'joined_on' => now($data['timezone'])->toDateString()]);
            $company->schedules()->create(['effective_from' => now($data['timezone'])->toDateString(), 'weekdays' => [1, 2, 3, 4, 5], 'start_time' => $data['start_time'], 'end_time' => $data['end_time']]);
            app(LegalAgreements::class)->record($request->user(), 'company', $company->customer_type === 'consumer' ? ['termeni-si-conditii', 'confidentialitate'] : ['acord-prelucrare-date', 'furnizori'], $company);

            return $company;
        }, 3);
        $request->session()->put('company_id', $company->id);

        return redirect('/dashboard')->with('success', 'Firma este pregătită. Invită primul coleg.');
    }

    public function switchCompany(Request $request): RedirectResponse
    {
        $data = $request->validate(['company_id' => 'required|integer']);
        $member = $request->user()->memberships()->where('active', true)->where('company_id', $data['company_id'])->firstOrFail();
        $request->session()->put('company_id', $member->company_id);

        return redirect('/dashboard');
    }

    public function saveDay(Request $request): RedirectResponse
    {
        $self = $this->context->membership($request);
        $data = $request->validate(['membership_id' => 'required|integer', 'date' => 'required|date_format:Y-m-d', 'extend_period' => 'sometimes|boolean', 'absence' => ['nullable', Rule::in(array_keys(AttendanceService::ABSENCES))], 'intervals' => 'nullable|array|max:20', 'intervals.*.kind' => 'required|in:work,break', 'intervals.*.start_at' => 'required|string|max:35', 'intervals.*.end_at' => 'required|string|max:35', 'reason' => 'nullable|string|max:1000', 'note' => 'nullable|string|max:1000']);
        $member = $self->company->memberships()->whereKey($data['membership_id'])->firstOrFail();
        abort_unless($self->role === 'owner' || $member->id === $self->id, 403);
        $this->attendance->save($member, $data, $request->user()->id, $self->role === 'owner');

        return back()->with('success', 'Ziua a fost înregistrată.');
    }

    public function review(Request $request, TimeEntry $entry): RedirectResponse
    {
        $company = $this->context->owner($request);
        abort_unless($entry->company_id === $company->id, 404);
        DB::transaction(function () use ($company, $entry, $request) {
            Company::lockForUpdate()->findOrFail($company->id);
            abort_if(MonthClosure::where('company_id', $company->id)->where('month', $entry->date->format('Y-m'))->whereNotNull('approved_at')->exists(), 423);
            $entry->update(['approved_at' => now(), 'approved_by' => $request->user()->id]);
            $this->attendance->audit($company, $request->user()->id, 'Înregistrare verificată', null, ['entry_id' => $entry->id]);
        }, 3);

        return back()->with('success', 'Înregistrarea a fost verificată.');
    }

    public function closeMonth(Request $request): RedirectResponse
    {
        $company = $this->context->owner($request);
        $data = $request->validate(['month' => 'required|date_format:Y-m', 'reopen' => 'required|boolean', 'reason' => 'nullable|string|max:1000']);
        $this->attendance->close($company, $data['month'], $request->user()->id, $data['reason'] ?? null, $data['reopen']);

        return back()->with('success', $data['reopen'] ? 'Luna a fost redeschisă.' : 'Luna este aprobată și blocată pentru editare.');
    }

    public function export(Request $request): BinaryFileResponse
    {
        $company = $this->context->owner($request);
        $data = $request->validate(['month' => 'required|date_format:Y-m']);
        $path = app(ExcelService::class)->build($company, $data['month']);
        $this->attendance->audit($company, $request->user()->id, 'Export Excel', null, ['month' => $data['month']]);

        return response()->download($path, 'ipontaj-'.$data['month'].'.xlsx')->deleteFileAfterSend(true);
    }

    public function settings(Request $request): RedirectResponse
    {
        $company = $this->context->owner($request);
        $data = $request->validate(['cif' => [! $company->is_demo && $company->cif ? 'required' : 'nullable', 'string', 'max:20'], 'name' => 'required|string|max:255', 'reminders' => 'required|boolean', 'accountant_email' => 'nullable|email|max:254', 'accountant_confirmed' => $request->filled('accountant_email') ? 'required|accepted' : 'nullable|boolean']);
        $identity = app(CompanyIdentity::class)->attributes($company->is_demo ? null : ($data['cif'] ?? null), $data['name'], $company);
        $company->update([...$identity, 'reminders' => $data['reminders'], 'accountant_email' => $data['accountant_email'] ?? null, 'accountant_confirmed_at' => ! empty($data['accountant_email']) && $request->boolean('accountant_confirmed') ? now() : null]);

        return back()->with('success', 'Setările au fost salvate.');
    }

    public function schedule(Request $request): RedirectResponse
    {
        $company = $this->context->owner($request);
        $data = $request->validate(['membership_id' => 'nullable|integer', 'effective_from' => 'required|date_format:Y-m-d|after_or_equal:today', 'weekdays' => 'required|array|min:1|max:7', 'weekdays.*' => 'required|integer|between:1,7|distinct', 'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i']);
        if (! empty($data['membership_id'])) {
            $company->memberships()->whereKey($data['membership_id'])->firstOrFail();
        }
        DB::transaction(function () use ($company, $data) {
            Company::lockForUpdate()->findOrFail($company->id);
            abort_if(MonthClosure::where('company_id', $company->id)->where('month', substr($data['effective_from'], 0, 7))->whereNotNull('approved_at')->exists(), 423);
            $company->schedules()->create($data);
        }, 3);

        return back()->with('success', 'Programul a fost salvat cu data de intrare în vigoare.');
    }

    public function member(Request $request, Membership $member): RedirectResponse
    {
        $company = $this->context->owner($request);
        abort_unless($member->company_id === $company->id, 404);
        abort_if($member->role === 'owner', 422, 'Proprietarul nu poate fi dezactivat aici.');
        $data = $request->validate(['active' => 'required|boolean']);
        DB::transaction(function () use ($company, $member, $data) {
            $company = Company::lockForUpdate()->findOrFail($company->id);
            $member->refresh();
            if ($data['active'] && ! $member->active && $company->occupiedSeats() >= $company->seatLimit()) {
                throw ValidationException::withMessages(['active' => 'Limita planului a fost atinsă.']);
            }$member->update(['active' => $data['active'], 'left_on' => $data['active'] ? null : now($company->timezone)->toDateString()]);
        }, 3);

        return back()->with('success', 'Starea colegului a fost actualizată.');
    }

    public function preferences(Request $request): RedirectResponse
    {
        $member = $this->context->membership($request);
        $data = $request->validate(['reminders' => 'required|boolean']);
        $member->update($data);

        return back()->with('success', 'Preferințele au fost salvate.');
    }
}
