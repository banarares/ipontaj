<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ContactMessage;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use App\Notifications\TeamInvitation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QueueProcessingTest extends TestCase
{
    use DatabaseMigrations;

    public function test_cron_worker_processes_contact_invitation_and_verification_without_smtp(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'array']);
        $user = User::factory()->unverified()->create();
        $company = Company::create(['name' => 'Firmă fictivă']);
        $token = 'fictitious-invitation-token';
        $invite = Invitation::create(['company_id' => $company->id, 'first_name' => 'Ana', 'last_name' => 'Exemplu', 'email' => 'ana@example.test', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);
        $contact = ContactMessage::create(['data' => ['first_name' => 'Ana', 'last_name' => 'Exemplu', 'email' => 'ana@example.test', 'subject' => 'Test', 'message' => 'Fictitious contact message']]);
        $user->sendEmailVerificationNotification();
        Notification::route('mail', 'office@example.test')->notify(new ContactMessageReceived($contact->id));
        Notification::route('mail', 'ana@example.test')->notify(new TeamInvitation($invite->id, url('/invite/'.$token)));
        $this->assertDatabaseCount('jobs', 3);
        $this->artisan('pontaj:queue:work')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertNotNull(Cache::get('pontaj:queue:last-finished'));
        $this->assertCount(3, app('mail.manager')->mailer('array')->getSymfonyTransport()->messages());
    }
}
