<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function team(User $owner): Company
    {
        $company = Company::create(['name' => 'Firmă fictivă']);
        $company->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'joined_on' => '2026-11-01']);

        return $company;
    }

    public function test_audited_actions_notify_actor_and_owner_without_disclosing_notes_or_foreign_activity(): void
    {
        $owner = User::factory()->create();
        $employee = User::factory()->create();
        $outsider = User::factory()->create();
        $company = $this->team($owner);
        $company->memberships()->create(['user_id' => $employee->id, 'joined_on' => '2026-11-01']);
        AuditEvent::create(['company_id' => $company->id, 'user_id' => $employee->id, 'action' => 'Zi înregistrată', 'after' => ['date' => '2026-11-25', 'note' => 'Private attendance note', 'intervals' => []]]);
        $this->assertDatabaseCount('user_notifications', 2);
        foreach ([$owner, $employee] as $user) {
            $this->actingAs($user)->getJson('/notifications')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('unread', 1)->assertJsonPath('items.0.title', 'Zi înregistrată')->assertJsonPath('items.0.url', '/today?date=2026-11-25')->assertDontSee('Private attendance note');
        }
        $this->actingAs($outsider)->getJson('/notifications')->assertJsonPath('unread', 0)->assertJsonCount(0, 'items');
    }

    public function test_read_operations_are_idempotent_scoped_to_user_and_do_not_expose_inactive_company(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $company = $this->team($owner);
        $this->team($other);
        $mine = UserNotification::create(['user_id' => $owner->id, 'company_id' => $company->id, 'title' => 'Notificare proprie', 'message' => 'Mesaj fictiv', 'url' => '/today']);
        $theirs = UserNotification::create(['user_id' => $other->id, 'title' => 'Notificare privată', 'message' => 'Mesaj fictiv', 'url' => '/today']);
        $this->actingAs($owner)->patch('/notifications/'.$theirs->id.'/read')->assertNotFound();
        $this->from('/dashboard')->patch('/notifications/'.$mine->id.'/read')->assertRedirect('/dashboard');
        $readAt = $mine->refresh()->read_at;
        $this->travel(1)->minutes();
        $this->patch('/notifications/'.$mine->id.'/read')->assertRedirect();
        $this->assertEquals($readAt, $mine->refresh()->read_at);
        $this->post('/notifications/read-all')->assertRedirect();
        $this->assertNull($theirs->refresh()->read_at);
        $company->memberships()->where('user_id', $owner->id)->update(['active' => false]);
        $this->getJson('/notifications')->assertJsonCount(0, 'items');
        $this->patch('/notifications/'.$mine->id.'/read')->assertNotFound();
    }

    public function test_notifications_rollback_with_failed_operation_and_guests_have_no_access(): void
    {
        $owner = User::factory()->create();
        $company = $this->team($owner);
        try {
            DB::transaction(function () use ($owner, $company) {
                AuditEvent::create(['company_id' => $company->id, 'user_id' => $owner->id, 'action' => 'Eveniment anulat']);
                throw new \RuntimeException('Rollback fixture');
            });
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('user_notifications', 0);
        $this->getJson('/notifications')->assertUnauthorized();
    }

    public function test_opening_a_notification_switches_only_to_its_authorized_company(): void
    {
        $owner = User::factory()->create();
        $first = $this->team($owner);
        $second = $this->team($owner);
        $notice = UserNotification::create(['user_id' => $owner->id, 'company_id' => $second->id, 'title' => 'Eveniment din a doua firmă', 'message' => 'Mesaj fictiv', 'url' => '/today?date=2026-11-25']);
        $this->actingAs($owner)->withSession(['company_id' => $first->id])->post('/notifications/'.$notice->id.'/open')->assertRedirect('/today?date=2026-11-25')->assertSessionHas('company_id', $second->id);
        $this->assertNotNull($notice->refresh()->read_at);
        $other = User::factory()->create();
        $this->actingAs($other)->withSession(['company_id' => $first->id])->post('/notifications/'.$notice->id.'/open')->assertNotFound();
    }
}
