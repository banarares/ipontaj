<?php

namespace App\Services;

use App\Jobs\SendAccountantExport;
use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\Membership;
use App\Models\MonthClosure;
use App\Models\Schedule;
use App\Models\TimeEntry;
use App\Models\TimeInterval;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public const ABSENCES = ['CO' => 'Concediu de odihnă', 'CM' => 'Concediu medical', 'CFP' => 'Concediu fără plată', 'A' => 'Altă absență'];

    public function schedule(Membership $member, string $date): ?Schedule
    {
        if ($member->company->relationLoaded('schedules')) {
            return $member->company->schedules
                ->filter(fn (Schedule $s) => (! $s->membership_id || $s->membership_id === $member->id) && $s->effective_from->toDateString() <= $date)
                ->sortBy([fn (Schedule $a, Schedule $b) => ($b->membership_id !== null) <=> ($a->membership_id !== null), fn (Schedule $a, Schedule $b) => $b->effective_from <=> $a->effective_from, fn (Schedule $a, Schedule $b) => $b->id <=> $a->id])
                ->first();
        }

        return Schedule::where('company_id', $member->company_id)->where(fn ($q) => $q->whereNull('membership_id')->orWhere('membership_id', $member->id))->whereDate('effective_from', '<=', $date)->orderByRaw('CASE WHEN membership_id IS NULL THEN 1 ELSE 0 END')->orderByDesc('effective_from')->orderByDesc('id')->first();
    }

    public function planned(Membership $member, string $date): int
    {
        if (! $member->tracked || ! $member->activeOn($date)) {
            return 0;
        }
        $s = $this->schedule($member, $date);
        if (! $s || ! in_array(Carbon::parse($date)->dayOfWeekIso, $s->weekdays)) {
            return 0;
        }
        $start = Carbon::parse($date.' '.$s->start_time, $member->company->timezone);
        $end = Carbon::parse($date.' '.$s->end_time, $member->company->timezone);
        if ($end <= $start) {
            $end = $end->addDay();
        }

        return (int) $start->diffInMinutes($end);
    }

    /** @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after */
    public function audit(Company $company, int $actor, string $action, ?array $before = null, ?array $after = null, ?string $reason = null): void
    {
        AuditEvent::create(['company_id' => $company->id, 'user_id' => $actor, 'action' => $action, 'before' => $before, 'after' => $after, 'reason' => $reason]);
    }

    /** @param array{date:string,absence?:string|null,intervals?:list<array{kind:string,start_at:string,end_at:string}>,reason?:string|null,note?:string|null} $data */
    public function save(Membership $member, array $data, int $actor, bool $owner): TimeEntry
    {
        return DB::transaction(function () use ($member, $data, $actor, $owner) {
            $company = Company::lockForUpdate()->findOrFail($member->company_id);
            $member->refresh();
            $date = $data['date'];
            $month = substr($date, 0, 7);
            abort_if(MonthClosure::where('company_id', $company->id)->where('month', $month)->whereNotNull('approved_at')->exists(), 423, 'Luna este aprobată. Proprietarul trebuie să o redeschidă.');
            abort_unless(($member->active || $owner) && $member->tracked, 403);
            if ($date > now($company->timezone)->toDateString() || ! $member->activeOn($date)) {
                $this->fail('date', 'Alege o zi din perioada activă, până astăzi.');
            }
            $existing = $member->entries()->whereDate('date', $date)->first();
            $correction = $owner && ($member->user_id !== $actor || $existing);
            if ($correction && empty($data['reason'])) {
                $this->fail('reason', 'Scrie motivul corecturii.');
            }
            $before = $existing?->load('intervals')->toArray();
            $rows = [];
            $minutes = 0;
            if (empty($data['absence'])) {
                foreach ($data['intervals'] ?? [] as $i => $row) {
                    foreach (['start_at', 'end_at'] as $field) {
                        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00[+-]\d{2}:\d{2}$/', $row[$field])) {
                            $this->fail("intervals.$i.$field", 'Folosește data, ora la minut și fusul orar explicit.');
                        }
                        try {
                            $instant = Carbon::parse($row[$field]);
                        } catch (\Throwable) {
                            $this->fail("intervals.$i.$field", 'Data sau ora nu este validă.');
                        }
                        if ($instant->setTimezone($company->timezone)->format('Y-m-d\TH:i:sP') !== $row[$field]) {
                            $this->fail("intervals.$i.$field", 'Ora sau offsetul nu există în fusul firmei. Verifică schimbarea orei.');
                        }
                    }
                    $start = Carbon::parse($row['start_at'])->utc();
                    $end = Carbon::parse($row['end_at'])->utc();
                    if ($start >= $end || $start->diffInMinutes($end) > 1440) {
                        $this->fail('intervals', 'Intervalul trebuie să aibă între 1 minut și 24 de ore.');
                    }
                    $local = $start->setTimezone($company->timezone);
                    if ($local->toDateString() < $date || $local->toDateString() > Carbon::parse($date)->addDay()->toDateString() || $end->setTimezone($company->timezone) > Carbon::parse($date, $company->timezone)->addDays(2)) {
                        $this->fail('intervals', 'Intervalele trebuie să aparțină zilei selectate sau turei de noapte următoare.');
                    }
                    $rows[] = ['kind' => $row['kind'], 'start_at' => $start, 'end_at' => $end];
                }
                $work = array_values(array_filter($rows, fn ($r) => $r['kind'] === 'work'));
                $breaks = array_values(array_filter($rows, fn ($r) => $r['kind'] === 'break'));
                if (! $work) {
                    $this->fail('intervals', 'Adaugă cel puțin un interval de lucru.');
                }
                if ($work[0]['start_at']->setTimezone($company->timezone)->toDateString() !== $date) {
                    $this->fail('intervals', 'Primul interval începe în ziua selectată.');
                }
                foreach ([$work, $breaks] as $group) {
                    foreach ($group as $i => $a) {
                        foreach ($group as $j => $b) {
                            if ($i < $j && $a['start_at'] < $b['end_at'] && $b['start_at'] < $a['end_at']) {
                                $this->fail('intervals', 'Intervalele de același tip se suprapun.');
                            }
                        }
                    }
                }
                foreach ($breaks as $b) {
                    if (! collect($work)->contains(fn ($w) => $b['start_at'] >= $w['start_at'] && $b['end_at'] <= $w['end_at'])) {
                        $this->fail('intervals', 'Pauza trebuie să fie cuprinsă într-un interval de lucru.');
                    }
                }
                foreach ($work as $w) {
                    $overlap = TimeInterval::where('kind', 'work')->where('start_at', '<', $w['end_at'])->where('end_at', '>', $w['start_at'])->whereHas('timeEntry', fn ($q) => $q->where('membership_id', $member->id)->when($existing, fn ($q) => $q->where('id', '!=', $existing->id)))->exists();
                    if ($overlap) {
                        $this->fail('intervals', 'Intervalul se suprapune cu o altă zi înregistrată.');
                    }
                    $minutes += (int) $w['start_at']->diffInMinutes($w['end_at']);
                }
                foreach ($breaks as $b) {
                    $minutes -= (int) $b['start_at']->diffInMinutes($b['end_at']);
                }
                if ($minutes < 1 || $minutes > 1440) {
                    $this->fail('intervals', 'Durata totală trebuie să fie între 1 minut și 24 de ore.');
                }
            }
            $self = $member->user_id === $actor;
            $entry = $existing ?? new TimeEntry(['membership_id' => $member->id, 'date' => $date]);
            $entry->fill(['company_id' => $company->id, 'absence' => $data['absence'] ?? null, 'minutes' => $minutes, 'note' => $data['note'] ?? null, 'confirmed_at' => $self ? now() : null, 'confirmed_by' => $self ? $actor : null, 'approved_at' => null, 'approved_by' => null]);
            $entry->save();
            $entry->intervals()->delete();
            $entry->intervals()->createMany($rows);
            $this->audit($company, $actor, $correction ? 'Corectură pontaj' : 'Zi înregistrată', $before, $entry->load('intervals')->toArray(), $data['reason'] ?? null);

            return $entry;
        }, 3);
    }

    /** @return array{rows:list<array{membership:Membership,days:list<array{date:string,planned:int,missing:bool,entry:TimeEntry|null}>,planned:int,confirmed:int,missing:int,pending:int}>,issues:list<array{membership_id:int,name:string,date:string,type:string,entry_id?:int}>,days:int} */
    public function summary(Company $company, string $month): array
    {
        $start = Carbon::parse($month.'-01', $company->timezone);
        $last = $start->endOfMonth();
        $today = now($company->timezone)->startOfDay();
        $company->loadMissing('schedules');
        $members = $company->memberships()->with(['user', 'periods'])->where('tracked', true)->whereDate('joined_on', '<=', $last)->where(fn ($q) => $q->whereNull('left_on')->orWhereDate('left_on', '>=', $start))->get();
        $entries = $company->entries()->with('intervals')->whereDate('date', '>=', $start->toDateString())->whereDate('date', '<=', $last->toDateString())->get();
        $rows = [];
        $issues = [];
        foreach ($members as $member) {
            $member->setRelation('company', $company);
            $days = [];
            $planned = 0;
            $confirmed = 0;
            $missing = 0;
            $pending = 0;
            for ($d = $start; $d <= $last; $d = $d->addDay()) {
                $date = $d->toDateString();
                $plan = $this->planned($member, $date);
                $planned += $plan;
                $e = $entries->first(fn ($e) => $e->membership_id === $member->id && $e->date->toDateString() === $date);
                $needs = $d <= $today && $plan > 0 && ! $e;
                if ($needs) {
                    $missing++;
                    $issues[] = ['membership_id' => $member->id, 'name' => $member->user->name, 'date' => $date, 'type' => 'Lipsește confirmarea'];
                }
                if ($e && ! $e->approved_at && ($e->absence || ! $e->confirmed_at)) {
                    $pending++;
                    $issues[] = ['membership_id' => $member->id, 'name' => $member->user->name, 'date' => $date, 'type' => $e->absence ? 'Absență de verificat' : 'Corectură de verificat', 'entry_id' => $e->id];
                }
                if ($e?->confirmed_at || $e?->approved_at) {
                    $confirmed += $e->minutes;
                }
                $days[] = ['date' => $date, 'planned' => $plan, 'missing' => $needs, 'entry' => $e];
            }
            $rows[] = ['membership' => $member, 'days' => $days, 'planned' => $planned, 'confirmed' => $confirmed, 'missing' => $missing, 'pending' => $pending];
        }

        return ['rows' => $rows, 'issues' => $issues, 'days' => $last->day];
    }

    public function close(Company $company, string $month, int $actor, ?string $reason = null, bool $reopen = false): void
    {
        DB::transaction(function () use ($company, $month, $actor, $reason, $reopen) {
            $company = Company::lockForUpdate()->findOrFail($company->id);
            if ($month > now($company->timezone)->format('Y-m')) {
                $this->fail('month', 'Nu poți aproba o lună viitoare.');
            }
            $closure = MonthClosure::firstOrCreate(['company_id' => $company->id, 'month' => $month]);
            if ($reopen) {
                if (! $reason) {
                    $this->fail('reason', 'Scrie motivul redeschiderii.');
                }
                if (! $closure->approved_at) {
                    return;
                }
            } else {
                if ($month >= now($company->timezone)->format('Y-m')) {
                    $this->fail('month', 'Aprobarea finală este disponibilă după încheierea lunii. Poți descărca oricând un export provizoriu.');
                }
                if ($closure->approved_at) {
                    return;
                }
                if (count($this->summary($company, $month)['issues'])) {
                    $this->fail('month', 'Rezolvă zilele lipsă și verifică absențele/corecturile înainte de aprobare.');
                }
            }
            $closure->update(['approved_at' => $reopen ? null : now(), 'approved_by' => $reopen ? null : $actor, 'approval_version' => $closure->approval_version + ($reopen ? 0 : 1)]);
            if (! $reopen) {
                $company->entries()->whereDate('date', '>=', $month.'-01')->whereDate('date', '<=', Carbon::parse($month.'-01')->endOfMonth()->toDateString())->update(['approved_at' => now(), 'approved_by' => $actor]);
            }
            $this->audit($company, $actor, $reopen ? 'Lună redeschisă' : 'Lună aprobată', null, ['month' => $month], $reason);
            if (! $reopen && ! $company->is_demo && $company->subscribed('default') && $company->accountant_confirmed_at) {
                SendAccountantExport::dispatch($closure->id)->afterCommit();
            }
        }, 3);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
