<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use App\Notifications\ResetAccountPassword;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_reset_and_contact_emails_share_romanian_branding_and_escape_content(): void
    {
        config(['app.url' => 'https://ipontaj.example']);
        $user = User::factory()->unverified()->create();
        $messages = [(new VerifyEmailAddress)->toMail($user), (new ResetAccountPassword('fixture'))->toMail($user)];
        $contact = ContactMessage::create(['data' => ['first_name' => 'Ana', 'last_name' => 'Exemplu', 'email' => 'ana@example.test', 'subject' => '<script>fixture</script>', 'message' => 'Fictitious contact message']]);
        $messages[] = (new ContactMessageReceived($contact->id))->toMail(new AnonymousNotifiable);
        foreach ($messages as $message) {
            $html = (string) app(Markdown::class)->render($message->markdown, $message->data());
            $this->assertStringContainsString('https://ipontaj.example/icons/icon-192.png', $html);
            $this->assertStringContainsString('iPontaj', $html);
            $this->assertStringContainsString('Toate drepturile rezervate', $html);
            $this->assertStringNotContainsString('Hello!', $html);
            $this->assertStringNotContainsString('Regards,', $html);
            $this->assertStringNotContainsString('<script>fixture</script>', $html);
        }
    }

    public function test_delivery_status_reports_counts_without_contents_or_credentials_and_sends_nothing(): void
    {
        config(['mail.mailers.smtp.password' => 'private-smtp-fixture', 'queue.default' => 'database']);
        Notification::fake();
        Artisan::call('pontaj:delivery:status');
        $output = Artisan::output();
        $this->assertStringNotContainsString('private-smtp-fixture', $output);
        $this->assertStringNotContainsString('payload', $output);
        $this->assertStringContainsString('pending_jobs', $output);
        Notification::assertNothingSent();
    }

    public function test_cron_worker_skips_when_another_worker_holds_the_lock(): void
    {
        $lock = Cache::lock('pontaj:queue:cron-worker', 180);
        $lock->get();
        try {
            $this->artisan('pontaj:queue:work')->expectsOutputToContain('Un worker este deja activ.')->assertSuccessful();
            $this->assertNull(Cache::get('pontaj:queue:last-started'));
        } finally {
            $lock->release();
        }
    }
}
