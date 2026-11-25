<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LegalAcceptance;
use App\Models\User;
use App\Services\DemoWorkspaces;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DemoLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(12, 0));
        config(['ipontaj.demo.session_minutes' => 60, 'ipontaj.demo.max_workspaces' => 20]);
        Notification::fake();
    }

    public function test_demo_creates_one_temporary_user_and_three_managed_colleagues_and_reuses_its_session(): void
    {
        $this->post('/demo')->assertRedirect('/dashboard');
        $owner = Auth::user();
        $company = $owner->memberships()->first()->company;
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('memberships', 4);
        $this->assertEquals(3, $company->memberships()->whereNull('user_id')->count());
        $this->assertEquals(0, $company->memberships()->where('reminders', true)->count());
        $this->assertFalse($company->reminders);
        $this->assertDatabaseHas('memberships', ['first_name' => 'Maria', 'last_name' => 'Ionescu', 'user_id' => null]);
        $this->assertDatabaseHas('time_entries', ['confirmed_by' => null, 'approved_by' => $owner->id]);
        $this->post('/demo')->assertRedirect('/dashboard');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('companies', 1);
        $this->get('/dashboard')->assertOk();
        Notification::assertNothingSent();
    }

    public function test_exit_deletes_only_its_demo_including_intervals_and_database_sessions(): void
    {
        $real = User::factory()->create();
        $realCompany = Company::create(['name' => 'Firmă reală păstrată']);
        $member = $realCompany->memberships()->create(['user_id' => $real->id, 'joined_on' => '2026-11-01']);
        $entry = $member->entries()->create(['company_id' => $realCompany->id, 'date' => '2026-11-24', 'minutes' => 480]);
        $other = app(DemoWorkspaces::class)->start();
        $otherCompany = $other->memberships()->first()->company;
        $this->post('/demo')->assertRedirect('/dashboard');
        $ownerId = Auth::id();
        $company = Auth::user()->memberships()->first()->company;
        DB::table('sessions')->insert(['id' => 'fictional-demo-session', 'user_id' => $ownerId, 'payload' => '', 'last_activity' => now()->timestamp]);
        $intervalIds = $company->entries()->with('intervals')->get()->flatMap(fn ($entry) => $entry->intervals->pluck('id'))->all();
        $this->post('/demo/exit')->assertRedirect('/register');
        $this->assertGuest();
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
        $this->assertDatabaseMissing('users', ['id' => $ownerId]);
        $this->assertDatabaseMissing('sessions', ['id' => 'fictional-demo-session']);
        $this->assertEquals(0, DB::table('time_intervals')->whereIn('id', $intervalIds)->count());
        $this->assertDatabaseHas('users', ['id' => $real->id, 'is_demo' => false]);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
        $this->assertDatabaseHas('companies', ['id' => $otherCompany->id]);
        $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'minutes' => 480]);
    }

    public function test_regular_logout_also_removes_demo_and_leaves_real_accounts_intact(): void
    {
        $real = User::factory()->create();
        $this->post('/demo');
        $id = Auth::id();
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $id]);
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseHas('users', ['id' => $real->id]);
    }

    public function test_expired_session_cannot_modify_attendance_and_can_start_over(): void
    {
        $this->post('/demo');
        $ownerId = Auth::id();
        $member = Auth::user()->memberships()->first();
        $this->travel(60)->minutes();
        $this->post('/entries', ['membership_id' => $member->id])->assertRedirect('/?demo=expired');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $ownerId]);
        $this->assertDatabaseCount('companies', 0);
        $this->post('/demo')->assertRedirect('/dashboard');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_capacity_limit_does_not_create_extra_rows_and_existing_visitors_keep_access(): void
    {
        config(['ipontaj.demo.max_workspaces' => 1]);
        $first = app(DemoWorkspaces::class)->start();
        $this->post('/demo')->assertRedirect('/')->assertSessionHasErrors('demo');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('companies', 1);
        $this->actingAs($first)->post('/demo')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_new_demo_prunes_expired_data_even_without_a_scheduler(): void
    {
        config(['ipontaj.demo.max_workspaces' => 1]);
        $old = app(DemoWorkspaces::class)->start();
        $this->travel(61)->minutes();
        $new = app(DemoWorkspaces::class)->start();
        $this->assertNotNull($new);
        $this->assertNotEquals($old->id, $new->id);
        $this->assertDatabaseMissing('users', ['id' => $old->id]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_preview_and_cleanup_cover_old_four_user_demos_and_orphans_without_touching_fresh_or_real_data(): void
    {
        $oldUsers = User::factory()->count(4)->create(['is_demo' => true]);
        $legacy = Company::create(['name' => 'Legacy demo', 'is_demo' => true]);
        foreach ($oldUsers as $user) {
            $legacy->memberships()->create(['user_id' => $user->id, 'joined_on' => '2026-11-01']);
        }
        $orphan = User::factory()->create(['is_demo' => true]);
        $real = User::factory()->create(['email' => 'demo-sounding@example.test']);
        $this->travel(61)->minutes();
        $fresh = User::factory()->create(['is_demo' => true]);
        $this->artisan('pontaj:clean-demo', ['--dry-run' => true])->expectsOutput('Previzualizare: 1 firme demo, 5 utilizatori demo.')->assertSuccessful();
        $this->assertDatabaseCount('users', 7);
        $this->artisan('pontaj:clean-demo')->expectsOutput('Șterse: 1 firme demo, 5 utilizatori demo.')->assertSuccessful();
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseHas('users', ['id' => $real->id]);
        $this->assertDatabaseHas('users', ['id' => $fresh->id]);
        $this->assertDatabaseMissing('users', ['id' => $orphan->id]);
        $this->artisan('pontaj:clean-demo')->expectsOutput('Șterse: 0 firme demo, 0 utilizatori demo.')->assertSuccessful();
    }

    public function test_cleanup_preserves_mixed_workspaces_real_memberships_and_contract_or_billing_references(): void
    {
        $real = User::factory()->create();
        $mixed = Company::create(['name' => 'Mixed', 'is_demo' => true]);
        $mixed->memberships()->create(['user_id' => $real->id, 'joined_on' => '2026-11-01']);
        $demoUserInRealCompany = User::factory()->create(['is_demo' => true]);
        $realCompany = Company::create(['name' => 'Real']);
        $realCompany->memberships()->create(['user_id' => $demoUserInRealCompany->id, 'joined_on' => '2026-11-01']);
        $stripe = Company::create(['name' => 'Stripe protected', 'is_demo' => true, 'stripe_id' => 'cus_fixture_protected']);
        $cif = Company::create(['name' => 'Fiscal protected', 'is_demo' => true, 'cif' => '1234']);
        $subscription = Company::create(['name' => 'Subscription protected', 'is_demo' => true]);
        $subscription->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_fixture_protected', 'stripe_status' => 'active']);
        $contract = Company::create(['name' => 'Contract protected', 'is_demo' => true]);
        $contractUser = User::factory()->create(['is_demo' => true]);
        LegalAcceptance::create(['company_id' => $contract->id, 'user_id' => $contractUser->id, 'purpose' => 'company', 'version' => 'fixture', 'documents' => [], 'facts' => [], 'accepted_at' => now()]);
        $this->travel(2)->days();
        $this->artisan('pontaj:clean-demo')->expectsOutput('Șterse: 0 firme demo, 0 utilizatori demo.')->assertSuccessful();
        $this->assertDatabaseCount('companies', 6);
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseHas('legal_acceptances', ['company_id' => $contract->id, 'user_id' => $contractUser->id]);
    }

    public function test_real_users_are_never_replaced_by_demo_or_removed_by_demo_exit(): void
    {
        $real = User::factory()->create();
        $this->actingAs($real)->post('/demo')->assertRedirect('/dashboard');
        $this->post('/demo/exit')->assertRedirect('/register');
        $this->assertAuthenticatedAs($real);
        $this->post('/logout')->assertRedirect('/');
        $this->assertDatabaseHas('users', ['id' => $real->id, 'is_demo' => false]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('companies', 0);
    }

    public function test_visitors_have_separate_workspaces_and_cannot_edit_each_others_demo(): void
    {
        $first = app(DemoWorkspaces::class)->start();
        $second = app(DemoWorkspaces::class)->start();
        $firstCompany = $first->memberships()->first()->company;
        $secondMember = $second->memberships()->first();
        $this->assertNotEquals($first->id, $second->id);
        $this->assertNotEquals($firstCompany->id, $secondMember->company_id);
        $this->actingAs($first)->post('/entries', ['membership_id' => $secondMember->id, 'date' => '2026-11-25', 'absence' => 'CO'])->assertNotFound();
        $this->assertDatabaseMissing('time_entries', ['membership_id' => $secondMember->id, 'date' => '2026-11-25']);
    }

    public function test_background_poll_expires_demo_with_json_instead_of_consuming_a_redirect_notice(): void
    {
        $this->post('/demo');
        $ownerId = Auth::id();
        $this->travel(60)->minutes();
        $this->getJson('/notifications')->assertUnauthorized()->assertJsonPath('reason', 'demo_expired');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $ownerId]);
        $this->assertDatabaseCount('companies', 0);
        $this->get('/?demo=expired')->assertOk();
    }
}
