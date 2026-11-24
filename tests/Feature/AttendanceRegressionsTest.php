<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Membership;
use App\Models\MonthClosure;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRegressionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-23 20:00', 'Europe/Bucharest'));
    }

    private function team(): array
    {
        $owner = User::factory()->create();
        $employee = User::factory()->create();
        $company = Company::create(['name' => 'Atelier fictiv', 'timezone' => 'Europe/Bucharest']);
        $ownerMember = $company->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'joined_on' => '2026-10-01']);
        $member = $company->memberships()->create(['user_id' => $employee->id, 'joined_on' => '2026-10-01']);
        $company->schedules()->create(['effective_from' => '2026-10-01', 'weekdays' => [1, 2, 3, 4, 5], 'start_time' => '09:00', 'end_time' => '17:00']);

        return [$owner, $employee, $company, $member, $ownerMember];
    }

    private function day(Membership $member, string $date, ?string $absence = null): array
    {
        return ['membership_id' => $member->id, 'date' => $date, 'absence' => $absence, 'reason' => 'Corectură fictivă verificată', 'intervals' => $absence ? [] : [['kind' => 'work', 'start_at' => $date.'T09:00:00+02:00', 'end_at' => $date.'T17:00:00+02:00']]];
    }

    public function test_reactivation_does_not_allow_work_or_absence_during_an_inactive_gap(): void
    {
        [$owner, $employee, $company, $member] = $this->team();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 18:00', $company->timezone));
        $this->actingAs($owner)->patch('/members/'.$member->id, ['active' => false])->assertSessionHasNoErrors();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:00', $company->timezone));
        $this->patch('/members/'.$member->id, ['active' => true])->assertSessionHasNoErrors();

        foreach ([$employee, $owner] as $actor) {
            foreach ([null, 'CO'] as $absence) {
                $this->actingAs($actor)->post('/entries', $this->day($member, '2026-11-12', $absence))->assertSessionHasErrors('date');
            }
        }
        $this->assertDatabaseCount('time_entries', 0);
        $this->assertDatabaseCount('time_intervals', 0);
        $this->assertDatabaseCount('audit_events', 0);
        $this->actingAs($employee)->get('/today?date=2026-11-12')->assertInertia(fn ($page) => $page->where('selectedDateActive', false));

        // Both ends of a recorded activity period remain inclusive.
        foreach (['2026-11-09', '2026-11-10', '2026-11-20'] as $date) {
            $this->post('/entries', $this->day($member, $date))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('time_entries', 3);
        $this->get('/today?date=2026-11-20')->assertInertia(fn ($page) => $page->where('selectedDateActive', true));
    }

    public function test_day_link_without_month_loads_that_month_and_only_employee_entries(): void
    {
        [$owner, $employee, $company, $member, $ownerMember] = $this->team();
        $old = $member->entries()->create(['company_id' => $company->id, 'date' => '2026-10-28', 'minutes' => 480, 'confirmed_at' => now(), 'confirmed_by' => $employee->id]);
        $member->entries()->create(['company_id' => $company->id, 'date' => '2026-11-23', 'minutes' => 120]);
        $ownerMember->entries()->create(['company_id' => $company->id, 'date' => '2026-10-28', 'minutes' => 240]);
        MonthClosure::create(['company_id' => $company->id, 'month' => '2026-10', 'approved_at' => now(), 'approved_by' => $owner->id]);

        $this->actingAs($employee)->get('/today?date=2026-10-28')->assertOk()->assertInertia(fn ($page) => $page
            ->where('month', '2026-10')->where('selectedDate', '2026-10-28')
            ->has('entries', 1)->where('entries.0.id', $old->id)
            ->where('closure.month', '2026-10')->where('selectedDateActive', true));
    }

    public function test_explicit_day_takes_precedence_over_a_conflicting_month(): void
    {
        [$owner, , $company, , $ownerMember] = $this->team();
        $old = $ownerMember->entries()->create(['company_id' => $company->id, 'date' => '2026-10-28', 'minutes' => 480]);
        $ownerMember->entries()->create(['company_id' => $company->id, 'date' => '2026-11-23', 'minutes' => 120]);
        $this->actingAs($owner)->get('/today?date=2026-10-28&month=2026-11')->assertInertia(fn ($page) => $page
            ->where('month', '2026-10')->where('selectedDate', '2026-10-28')
            ->has('entries', 1)->where('entries.0.id', $old->id)->where('summary.days', 31));
    }

    public function test_month_only_and_default_day_stay_in_the_loaded_month(): void
    {
        [, $employee] = $this->team();
        $this->actingAs($employee)->get('/today?month=2026-10')->assertInertia(fn ($page) => $page->where('month', '2026-10')->where('selectedDate', '2026-10-01'));
        $this->get('/today')->assertInertia(fn ($page) => $page->where('month', '2026-11')->where('selectedDate', '2026-11-23'));
    }
}
