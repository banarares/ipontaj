<?php

namespace Tests\Feature;

use App\Jobs\SendReminder;
use App\Models\Company;
use App\Models\Membership;
use App\Models\MonthClosure;
use App\Models\User;
use App\Notifications\PontajMessage;
use App\Services\AttendanceService;
use App\Services\ExcelService;
use App\Services\TeamService;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PontajTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->travelTo(Carbon::parse('2026-11-23 20:00:00', 'Europe/Bucharest'));
    }

    private function team(bool $owner = true): array
    {
        $u = User::factory()->create();
        $c = Company::create(['name' => 'Firma fictivă', 'timezone' => 'Europe/Bucharest']);
        $m = $c->memberships()->create(['user_id' => $u->id, 'role' => $owner ? 'owner' : 'employee', 'joined_on' => '2026-01-01']);
        $c->schedules()->create(['effective_from' => '2026-01-01', 'weekdays' => [1, 2, 3, 4, 5], 'start_time' => '09:00', 'end_time' => '17:00']);

        return [$u, $c, $m];
    }

    private function day(Membership $m, string $date = '2026-11-23', array $intervals = []): array
    {
        return ['membership_id' => $m->id, 'date' => $date, 'absence' => null, 'intervals' => $intervals ?: [['kind' => 'work', 'start_at' => $date.'T09:00:00+02:00', 'end_at' => $date.'T17:00:00+02:00']]];
    }

    public function test_owner_employee_invitation_confirmation_approval_and_excel_flow(): void
    {
        $this->travelTo(Carbon::parse('2026-11-30 19:00', 'Europe/Bucharest'));
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/companies', ['dpa_accepted' => true, 'legal_version' => config('legal.version'), 'cif' => '1234', 'name' => 'Echipa nouă', 'timezone' => 'Europe/Bucharest', 'start_time' => '09:00', 'end_time' => '17:00', 'include_owner' => false, 'schedule_confirmed' => true])->assertRedirect('/dashboard');
        $company = Company::first();
        $this->post('/invitations', ['first_name' => 'Ana', 'last_name' => 'Exemplu', 'email' => 'ana@example.test', 'send_email' => false])->assertSessionHasNoErrors()->assertSessionHas('invitation_url');
        $url = session('invitation_url');
        $employee = User::factory()->create(['email' => 'ana@example.test']);
        $this->actingAs($employee)->post(parse_url($url, PHP_URL_PATH))->assertRedirect('/today');
        $member = Membership::where('user_id', $employee->id)->first();
        $this->post('/entries', $this->day($member, '2026-11-30'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('time_entries', ['membership_id' => $member->id, 'minutes' => 480, 'confirmed_by' => $employee->id, 'approved_by' => null]);
        $this->travelTo(Carbon::parse('2026-12-01 10:00', 'Europe/Bucharest'));
        $this->actingAs($owner)->post('/months/close', ['month' => '2026-11', 'reopen' => false])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('month_closures', ['company_id' => $company->id, 'month' => '2026-11', 'approved_by' => $owner->id]);
        $this->get('/exports?month=2026-11')->assertDownload('ipontaj-2026-11.xlsx');
    }

    public function test_company_scope_protects_writes_reviews_export_and_members(): void
    {
        [$u,$c,$m] = $this->team();
        [$other,$foreign,$fm] = $this->team();
        $entry = app(AttendanceService::class)->save($fm, $this->day($fm), $other->id, true);
        $this->actingAs($u)->post('/entries', $this->day($fm))->assertNotFound();
        $this->post('/entries/'.$entry->id.'/review')->assertNotFound();
        $this->patch('/members/'.$fm->id, ['active' => false])->assertNotFound();
        $this->post('/companies/switch', ['company_id' => $foreign->id])->assertNotFound();
        $this->get('/dashboard')->assertInertia(fn ($p) => $p->where('company.id', $c->id)->where('members', fn ($members) => count($members) === 1));
        $path = app(ExcelService::class)->build($c, '2026-11');
        $book = IOFactory::load($path);
        $this->assertStringNotContainsString($other->name, json_encode($book->getActiveSheet()->toArray()));
        unlink($path);
    }

    public function test_employee_only_sees_own_records_and_cannot_manage_company(): void
    {
        [$u,$c,$m] = $this->team(false);
        $colleague = User::factory()->create();
        $cm = $c->memberships()->create(['user_id' => $colleague->id, 'joined_on' => '2026-01-01']);
        app(AttendanceService::class)->save($cm, $this->day($cm), $colleague->id, false);
        $this->actingAs($u)->get('/today')->assertInertia(fn ($p) => $p->where('entries', [])->where('members', fn ($members) => count($members) === 1));
        $this->post('/entries', $this->day($cm))->assertForbidden();
        foreach (['/team', '/month', '/settings', '/billing', '/exports?month=2026-11', '/company/data'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/invitations', ['first_name' => 'A', 'last_name' => 'Exemplu', 'email' => 'a@example.test'])->assertForbidden();
        $this->post('/months/close', ['month' => '2026-11', 'reopen' => false])->assertForbidden();
    }

    public function test_invitation_retry_is_idempotent_and_requires_matching_email(): void
    {
        [$owner,$c] = $this->team();
        $s = app(TeamService::class);
        [$i,$token] = $s->invite($c, ['first_name' => 'Invitat', 'last_name' => 'Exemplu', 'email' => 'invitat@example.test']);
        $user = User::factory()->create(['email' => 'invitat@example.test']);
        $m = $s->accept($token, $user);
        $again = $s->accept($token, $user);
        $this->assertSame($m->id, $again->id);
        $this->assertSame(2, $c->memberships()->count());
        $wrong = User::factory()->create();
        $this->actingAs($wrong)->post('/invite/'.$token)->assertForbidden();
    }

    public function test_invitation_resend_rotates_token_without_reserving_an_extra_seat(): void
    {
        [$owner,$c] = $this->team();
        $s = app(TeamService::class);
        [$i,$token] = $s->invite($c, ['first_name' => 'Invitat', 'last_name' => 'Exemplu', 'email' => 'invitat@example.test']);
        [$same,$new] = $s->invite($c, ['first_name' => 'Invitat', 'last_name' => 'Exemplu', 'email' => 'invitat@example.test']);
        $this->assertSame($i->id, $same->id);
        $this->assertNotSame($token, $new);
        $this->assertSame(2, $c->occupiedSeats());
        $this->get('/invite/'.$token)->assertNotFound();
        $this->actingAs($owner)->delete('/invitations/'.$i->id)->assertSessionHasNoErrors();
        $this->get('/invite/'.$new)->assertGone();
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        [$owner,$c] = $this->team();
        [$i,$token] = app(TeamService::class)->invite($c, ['first_name' => 'Invitat', 'last_name' => 'Exemplu', 'email' => 'invitat@example.test']);
        $i->update(['expires_at' => now()->subSecond()]);
        $this->actingAs(User::factory()->create(['email' => 'invitat@example.test']))->post('/invite/'.$token)->assertGone();
    }

    public function test_active_invitations_count_toward_plan_limit_and_reactivation(): void
    {
        config(['ipontaj.billing_enabled' => true]);
        [$owner,$c] = $this->team();
        $s = app(TeamService::class);
        for ($i = 0; $i < 4; $i++) {
            $s->invite($c, ['first_name' => 'Invitat', 'last_name' => 'Exemplu', 'email' => "invitat$i@example.test"]);
        }
        $this->actingAs($owner)->post('/invitations', ['first_name' => 'Peste', 'last_name' => 'limită', 'email' => 'extra@example.test'])->assertSessionHasErrors('email');
        $inactive = User::factory()->create();
        $m = $c->memberships()->create(['user_id' => $inactive->id, 'active' => false, 'joined_on' => '2026-01-01', 'left_on' => '2026-01-02']);
        $this->patch('/members/'.$m->id, ['active' => true])->assertSessionHasErrors('active');
        $this->assertSame(5, $c->occupiedSeats());
    }

    public function test_overnight_work_and_explicit_break_preserve_minute_precision(): void
    {
        [$u,$c,$m] = $this->team(false);
        $data = $this->day($m, '2026-11-22', [
            ['kind' => 'work', 'start_at' => '2026-11-22T22:07:00+02:00', 'end_at' => '2026-11-23T06:03:00+02:00'],
            ['kind' => 'break', 'start_at' => '2026-11-23T01:00:00+02:00', 'end_at' => '2026-11-23T01:23:00+02:00']]);
        $this->actingAs($u)->post('/entries', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('time_entries', ['membership_id' => $m->id, 'minutes' => 453]);
    }

    public function test_spring_dst_uses_elapsed_time(): void
    {
        [$u,$c,$m] = $this->team(false);
        $this->actingAs($u)->post('/entries', $this->day($m, '2026-03-29', [
            ['kind' => 'work', 'start_at' => '2026-03-29T01:00:00+02:00', 'end_at' => '2026-03-29T05:00:00+03:00']]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('time_entries', ['minutes' => 180]);
    }

    public function test_fall_dst_distinguishes_both_occurrences_of_the_same_wall_clock_time(): void
    {
        [$u,$c,$m] = $this->team(false);
        $this->actingAs($u)->post('/entries', $this->day($m, '2026-10-25', [
            ['kind' => 'work', 'start_at' => '2026-10-25T03:15:00+03:00', 'end_at' => '2026-10-25T03:45:00+02:00']]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('time_entries', ['minutes' => 90]);
    }

    public function test_nonexistent_local_time_or_wrong_offset_is_rejected(): void
    {
        [$u,$c,$m] = $this->team(false);
        $this->actingAs($u)->post('/entries', $this->day($m, '2026-03-29', [
            ['kind' => 'work', 'start_at' => '2026-03-29T03:15:00+02:00', 'end_at' => '2026-03-29T05:00:00+03:00']]))->assertSessionHasErrors('intervals.0.start_at');
        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_overlap_within_day_and_across_midnight_is_rejected(): void
    {
        [$u,$c,$m] = $this->team(false);
        $this->actingAs($u)->post('/entries', $this->day($m, '2026-11-22', [
            ['kind' => 'work', 'start_at' => '2026-11-22T22:00:00+02:00', 'end_at' => '2026-11-23T06:00:00+02:00']]))->assertSessionHasNoErrors();
        $this->post('/entries', $this->day($m, '2026-11-23', [['kind' => 'work', 'start_at' => '2026-11-23T05:59:00+02:00', 'end_at' => '2026-11-23T08:00:00+02:00']]))->assertSessionHasErrors('intervals');
        $this->post('/entries', $this->day($m, '2026-11-21', [
            ['kind' => 'work', 'start_at' => '2026-11-21T09:00:00+02:00', 'end_at' => '2026-11-21T12:00:00+02:00'], ['kind' => 'work', 'start_at' => '2026-11-21T11:59:00+02:00', 'end_at' => '2026-11-21T13:00:00+02:00']]))->assertSessionHasErrors('intervals');
    }

    public function test_invalid_break_duration_and_future_date_are_rejected(): void
    {
        [$u,$c,$m] = $this->team(false);
        $data = $this->day($m);
        $data['intervals'][] = ['kind' => 'break', 'start_at' => '2026-11-23T18:00:00+02:00', 'end_at' => '2026-11-23T18:30:00+02:00'];
        $this->actingAs($u)->post('/entries', $data)->assertSessionHasErrors('intervals');
        $data = $this->day($m);
        $data['intervals'][0]['end_at'] = $data['intervals'][0]['start_at'];
        $this->post('/entries', $data)->assertSessionHasErrors('intervals');
        $this->post('/entries', $this->day($m, '2026-11-24'))->assertSessionHasErrors('date');
    }

    public function test_absence_requires_review_and_owner_correction_requires_reason_and_audit(): void
    {
        [$owner,$c] = $this->team();
        $employee = User::factory()->create();
        $m = $c->memberships()->create(['user_id' => $employee->id, 'joined_on' => '2026-01-01']);
        $this->actingAs($employee)->post('/entries', ['membership_id' => $m->id, 'date' => '2026-11-23', 'absence' => 'CO', 'intervals' => []])->assertSessionHasNoErrors();
        $entry = $m->entries()->first();
        $this->assertNull($entry->approved_at);
        $this->actingAs($owner)->post('/entries/'.$entry->id.'/review')->assertSessionHasNoErrors();
        $this->assertNotNull($entry->refresh()->approved_at);
        $this->post('/entries', $this->day($m))->assertSessionHasErrors('reason');
        $this->post('/entries', [...$this->day($m), 'reason' => 'Ore reale corectate'])->assertSessionHasNoErrors();
        $entry->refresh();
        $this->assertNull($entry->confirmed_at);
        $this->assertNull($entry->approved_at);
        $this->assertDatabaseHas('audit_events', ['company_id' => $c->id, 'action' => 'Corectură pontaj', 'reason' => 'Ore reale corectate']);
    }

    public function test_approved_month_is_locked_and_reopening_is_audited(): void
    {
        [$u,$c,$m] = $this->team();
        MonthClosure::create(['company_id' => $c->id, 'month' => '2026-11', 'approved_at' => now(), 'approved_by' => $u->id]);
        $this->actingAs($u)->post('/entries', $this->day($m))->assertStatus(423);
        $this->post('/months/close', ['month' => '2026-11', 'reopen' => true])->assertSessionHasErrors('reason');
        $this->post('/months/close', ['month' => '2026-11', 'reopen' => true, 'reason' => 'Corecție solicitată'])->assertSessionHasNoErrors();
        $this->post('/entries', $this->day($m))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_events', ['action' => 'Lună redeschisă', 'reason' => 'Corecție solicitată']);
    }

    public function test_cannot_approve_incomplete_or_current_month(): void
    {
        [$u,$c] = $this->team();
        $this->actingAs($u)->post('/months/close', ['month' => '2026-10', 'reopen' => false])->assertSessionHasErrors('month');
        $this->post('/months/close', ['month' => '2026-11', 'reopen' => false])->assertSessionHasErrors('month');
    }

    public function test_new_schedule_does_not_modify_previous_months(): void
    {
        [$u,$c,$m] = $this->team();
        $s = app(AttendanceService::class);
        $this->assertSame(480, $s->planned($m, '2026-10-12'));
        $this->actingAs($u)->post('/schedules', ['effective_from' => '2026-11-23', 'membership_id' => $m->id, 'weekdays' => [1, 2, 3, 4, 5], 'start_time' => '09:00', 'end_time' => '13:00'])->assertSessionHasNoErrors();
        $this->assertSame(480, $s->planned($m, '2026-10-12'));
        $this->assertSame(240, $s->planned($m, '2026-11-23'));
    }

    public function test_inactive_gaps_remain_unplanned_after_reactivation(): void
    {
        [$owner,$c] = $this->team();
        $user = User::factory()->create();
        $m = $c->memberships()->create(['user_id' => $user->id, 'joined_on' => '2026-11-01']);
        $m->update(['active' => false, 'left_on' => '2026-11-23']);
        $this->travelTo(Carbon::parse('2026-11-30', 'Europe/Bucharest'));
        $m->update(['active' => true, 'left_on' => null]);
        $s = app(AttendanceService::class);
        $this->assertSame(0, $s->planned($m, '2026-11-25'));
        $this->assertSame(480, $s->planned($m, '2026-11-30'));
    }

    public function test_excel_has_two_sheets_and_neutralizes_text_formulas(): void
    {
        [$u,$c,$m] = $this->team();
        $u->update(['name' => '=HYPERLINK("https://example.test")']);
        $c->update(['name' => '+CMD']);
        $this->assertCount(1, app(AttendanceService::class)->summary($c, '2026-11')['rows']);
        $path = app(ExcelService::class)->build($c, '2026-11');
        $book = IOFactory::load($path);
        $this->assertSame(['Centralizator', 'Intervale detaliate'], $book->getSheetNames());
        $this->assertSame('s', $book->getActiveSheet()->getCell('B6')->getDataType());
        $this->assertSame($u->name, $book->getActiveSheet()->getCell('B6')->getValue());
        $this->assertStringContainsString('PROVIZORIU', $book->getActiveSheet()->getCell('A2')->getValue());
        $this->assertSame('?', $book->getActiveSheet()->getCell('D6')->getValue());
        unlink($path);
    }

    public function test_reminder_retry_is_deduplicated_and_confirmed_days_are_skipped(): void
    {
        Notification::fake();
        [$u,$c,$m] = $this->team(false);
        $job = new SendReminder($c->id, $m->id, '2026-11-23');
        $job->handle(app(AttendanceService::class));
        $job->handle(app(AttendanceService::class));
        Notification::assertSentToTimes($u, PontajMessage::class, 1);
        app(AttendanceService::class)->save($m, $this->day($m, '2026-11-20'), $u->id, false);
        (new SendReminder($c->id, $m->id, '2026-11-20'))->handle(app(AttendanceService::class));
        Notification::assertSentToTimes($u, PontajMessage::class, 1);
    }

    public function test_demo_is_isolated_and_cannot_send_invitations(): void
    {
        $this->post('/demo')->assertRedirect('/dashboard');
        $user = auth()->user();
        $this->assertTrue((bool) $user->is_demo);
        $this->get('/dashboard')->assertOk();
        $this->post('/invitations', ['first_name' => 'A', 'last_name' => 'Exemplu', 'email' => 'a@example.test'])->assertForbidden();
        $this->post('/companies', ['name' => 'Interzis'])->assertForbidden();
        $this->get('/exports?month=2026-11')->assertDownload();
    }

    public function test_demo_cleanup_preserves_real_companies(): void
    {
        [$u,$c] = $this->team();
        $this->post('/demo');
        $this->travel(2)->days();
        $this->artisan('pontaj:clean-demo')->assertSuccessful();
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseHas('companies', ['id' => $c->id]);
    }

    public function test_duplicate_signed_stripe_webhooks_are_processed_once_and_unsigned_rejected(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);
        $body = json_encode(['id' => 'evt_test_duplicate', 'type' => 'customer.updated', 'data' => ['object' => ['id' => 'cus_unknown', 'email' => 'a@example.test']]]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test');
        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t=$timestamp,v1=$signature"], $body)->assertOk();
        }
        $this->assertDatabaseCount('webhook_events', 1);
        $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't=0,v1=bad'], $body)->assertForbidden();
    }

    public function test_billing_cannot_be_enabled_by_return_url_and_unconfigured_checkout_is_closed(): void
    {
        [$u,$c] = $this->team();
        $this->actingAs($u)->get('/billing?checkout=returned')->assertOk();
        $this->assertFalse($c->subscribed('default'));
        $this->post('/billing/checkout')->assertStatus(503);
    }
}
