<?php

namespace Tests\Feature;

use App\Jobs\SendReminder;
use App\Models\Company;
use App\Models\Membership;
use App\Models\MonthClosure;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\ExcelService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ManagedAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
        config(['inertia.ssr.enabled' => false]);
        $this->travelTo(CarbonImmutable::parse('2026-11-24 19:00', 'Europe/Bucharest'));
    }

    private function team(): array
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'Echipă fictivă', 'timezone' => 'Europe/Bucharest']);
        $member = $company->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'joined_on' => '2026-11-24']);
        $company->schedules()->create(['effective_from' => '2026-11-01', 'weekdays' => [1, 2, 3, 4, 5], 'start_time' => '09:00', 'end_time' => '17:00']);

        return [$owner, $company, $member];
    }

    private function profile(): array
    {
        return ['first_name' => 'Ion', 'last_name' => 'Fictiv', 'joined_on' => '2026-11-01', 'responsibility_confirmed' => true];
    }

    private function day(Membership $member, string $date = '2026-11-23'): array
    {
        return ['membership_id' => $member->id, 'date' => $date, 'intervals' => [['kind' => 'work', 'start_at' => $date.'T09:00:00+02:00', 'end_at' => $date.'T17:00:00+02:00']]];
    }

    public function test_managed_colleague_requires_explicit_responsibility_and_does_not_create_an_account(): void
    {
        Notification::fake();
        [$owner, $company] = $this->team();
        $this->actingAs($owner)->post('/members', [...$this->profile(), 'responsibility_confirmed' => false])->assertSessionHasErrors('responsibility_confirmed');
        $this->post('/members', $this->profile())->assertSessionHasNoErrors();
        $member = $company->memberships()->whereNull('user_id')->firstOrFail();
        $this->assertSame('Ion Fictiv', $member->user->name);
        $this->assertNull($member->user->email);
        $this->assertFalse($member->user->exists);
        $this->assertFalse($member->reminders);
        $this->assertTrue($member->managed);
        $this->assertSame($owner->id, $member->managed_confirmed_by);
        $this->assertNotNull($member->managed_confirmed_at);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('invitations', 0);
        $this->assertDatabaseHas('audit_events', ['user_id' => $owner->id, 'action' => 'Coleg fără cont adăugat']);
        $this->get('/team')->assertInertia(fn ($page) => $page->has('members', 2)->where('members.1.managed', true)->where('members.1.user.name', 'Ion Fictiv')->where('members.1.user.email', null));
        $export = json_decode($this->get('/company/data')->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Ion', $export['members'][1]['first_name']);
        Notification::assertNothingSent();
    }

    public function test_owner_records_managed_attendance_without_claiming_employee_confirmation_and_can_export_names(): void
    {
        [$owner, $company] = $this->team();
        $this->actingAs($owner)->post('/members', $this->profile())->assertSessionHasNoErrors();
        $member = $company->memberships()->whereNull('user_id')->firstOrFail();
        $this->post('/entries', $this->day($member))->assertSessionHasNoErrors();
        $entry = $member->entries()->firstOrFail();
        $this->assertNull($entry->confirmed_at);
        $this->assertNull($entry->confirmed_by);
        $this->assertNotNull($entry->approved_at);
        $this->assertSame($owner->id, $entry->approved_by);
        $this->assertDatabaseHas('audit_events', ['action' => 'Pontaj înregistrat de responsabil', 'user_id' => $owner->id]);
        $summary = app(AttendanceService::class)->summary($company, '2026-11');
        $row = collect($summary['rows'])->first(fn ($row) => $row['membership']->id === $member->id);
        $this->assertSame(480, $row['confirmed']);
        $path = app(ExcelService::class)->build($company, '2026-11');
        try {
            $book = IOFactory::load($path);
            $this->assertSame('Fictiv', $book->getSheet(0)->getCell('A7')->getValue());
            $this->assertSame('Ion', $book->getSheet(0)->getCell('B7')->getValue());
        } finally {
            unlink($path);
        }
        $this->post('/entries', $this->day($member))->assertSessionHasErrors('reason');
        $this->post('/entries', [...$this->day($member), 'reason' => 'Ore verificate de responsabil'])->assertSessionHasNoErrors();
    }

    public function test_managed_profiles_are_owner_only_and_attendance_remains_company_scoped(): void
    {
        [$owner, $company] = $this->team();
        $employee = User::factory()->create();
        $company->memberships()->create(['user_id' => $employee->id, 'joined_on' => '2026-11-01']);
        $this->actingAs($employee)->post('/members', $this->profile())->assertForbidden();
        $this->actingAs($owner)->post('/members', $this->profile())->assertSessionHasNoErrors();
        $managed = $company->memberships()->whereNull('user_id')->firstOrFail();
        $this->actingAs($employee)->post('/entries', $this->day($managed))->assertForbidden();
        [$otherOwner] = $this->team();
        $this->actingAs($otherOwner)->post('/entries', $this->day($managed))->assertNotFound();
        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_managed_profiles_and_pending_invitations_share_the_seat_limit(): void
    {
        config(['ipontaj.billing_enabled' => false, 'ipontaj.self_hosted_seat_limit' => 2]);
        [$owner, $company] = $this->team();
        $this->actingAs($owner)->post('/invitations', ['first_name' => 'Ana', 'last_name' => 'Fictiv', 'email' => 'pending@example.test'])->assertSessionHasNoErrors();
        $this->post('/members', $this->profile())->assertSessionHasErrors('first_name');
        $this->assertSame(2, $company->occupiedSeats());
        $this->delete('/invitations/'.$company->invitations()->firstOrFail()->id)->assertSessionHasNoErrors();
        $this->post('/members', $this->profile())->assertSessionHasNoErrors();
        $this->post('/invitations', ['first_name' => 'Ana', 'last_name' => 'Fictiv', 'email' => 'another@example.test'])->assertSessionHasErrors('email');
        $managed = $company->memberships()->whereNull('user_id')->firstOrFail();
        $this->patch('/members/'.$managed->id, ['active' => false])->assertSessionHasNoErrors();
        $this->assertSame(1, $company->occupiedSeats());
    }

    public function test_no_reminder_is_sent_to_a_managed_profile_even_if_its_preference_was_enabled(): void
    {
        Notification::fake();
        [$owner, $company] = $this->team();
        $this->actingAs($owner)->post('/members', $this->profile())->assertSessionHasNoErrors();
        $member = $company->memberships()->whereNull('user_id')->firstOrFail();
        $member->update(['reminders' => true]);
        (new SendReminder($company->id, $member->id, '2026-11-23'))->handle(app(AttendanceService::class));
        Notification::assertNothingSent();
        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    public function test_owner_can_explicitly_correct_the_first_active_date_with_an_audit_reason(): void
    {
        [$owner, , $member] = $this->team();
        $this->actingAs($owner)->post('/entries', $this->day($member, '2026-11-01'))->assertSessionHasErrors('extend_period');
        $this->post('/entries', [...$this->day($member, '2026-11-01'), 'extend_period' => true])->assertSessionHasErrors('reason');
        $this->post('/entries', [...$this->day($member, '2026-11-01'), 'extend_period' => true, 'reason' => 'Persoana lucra deja înainte de crearea contului.'])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-01', $member->fresh()->joined_on->toDateString());
        $this->assertTrue($member->fresh()->activeOn('2026-11-01'));
        $this->assertDatabaseHas('audit_events', ['action' => 'Început pontaj corectat', 'user_id' => $owner->id]);
        $this->assertDatabaseHas('time_entries', ['membership_id' => $member->id, 'confirmed_by' => $owner->id, 'minutes' => 480]);
    }

    public function test_extending_the_start_does_not_open_inactive_gaps_or_closed_months(): void
    {
        [$owner, $company, $member] = $this->team();
        $member->update(['joined_on' => '2026-10-01']);
        $member->periods()->delete();
        $member->periods()->createMany([['start_on' => '2026-10-01', 'end_on' => '2026-11-10'], ['start_on' => '2026-11-20']]);
        $this->actingAs($owner)->post('/entries', [...$this->day($member, '2026-11-12'), 'extend_period' => true, 'reason' => 'Nu trebuie să elimine pauza'])->assertSessionHasErrors('date');
        MonthClosure::create(['company_id' => $company->id, 'month' => '2026-10', 'approved_at' => now(), 'approved_by' => $owner->id]);
        $this->post('/entries', [...$this->day($member, '2026-09-30'), 'extend_period' => true, 'reason' => 'Nu trebuie să afecteze o lună închisă'])->assertStatus(423);
        $this->assertSame('2026-10-01', $member->fresh()->joined_on->toDateString());
        $this->assertDatabaseCount('time_entries', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_employee_cannot_extend_activity_and_invalid_intervals_rollback_an_owner_extension(): void
    {
        [$owner, $company, $member] = $this->team();
        $employee = User::factory()->create();
        $employeeMember = $company->memberships()->create(['user_id' => $employee->id, 'joined_on' => '2026-11-24']);
        $this->actingAs($employee)->post('/entries', [...$this->day($employeeMember), 'extend_period' => true, 'reason' => 'Fără permisiune'])->assertSessionHasErrors('date');
        $this->actingAs($owner)->post('/entries', [...$this->day($member), 'extend_period' => true, 'reason' => 'Interval invalid', 'intervals' => []])->assertSessionHasErrors('intervals');
        $this->assertSame('2026-11-24', $member->fresh()->joined_on->toDateString());
        $this->assertSame('2026-11-24', $member->periods()->firstOrFail()->start_on);
        $this->post('/entries', [...$this->day($member, '2026-11-25'), 'extend_period' => true, 'reason' => 'Zi viitoare'])->assertSessionHasErrors('date');
        $this->assertDatabaseCount('audit_events', 0);
    }
}
