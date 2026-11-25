<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ConfirmedEmailAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_leaves_guest_verification_works_on_another_browser_and_login_is_required(): void
    {
        Notification::fake();
        $this->post('/register', ['first_name' => 'Ana', 'last_name' => 'Exemplu', 'email' => 'ana@example.test', 'password' => 'Molid!Vapor7Nori$42', 'password_confirmation' => 'Molid!Vapor7Nori$42', 'terms_accepted' => true, 'legal_version' => config('legal.version')])->assertRedirect('/email/verify');
        $this->assertGuest();
        $user = User::firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailAddress::class);
        $this->get('/onboarding')->assertRedirect('/login');
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->flushSession();
        $this->get($link)->assertRedirect('/login');
        $this->assertGuest();
        $this->assertTrue($user->refresh()->hasVerifiedEmail());
        $this->post('/login', ['email' => $user->email, 'password' => 'Molid!Vapor7Nori$42'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_unverified_login_and_old_session_cannot_access_or_mutate_a_workspace(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email')->assertSessionMissing('pending_verification');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/email/verify');
        $this->assertGuest();
        foreach (['/companies', '/entries', '/invitations', '/billing/checkout'] as $path) {
            $this->actingAs($user)->post($path, [])->assertRedirect('/email/verify');
            $this->assertGuest();
        }
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('time_entries', 0);
        $this->assertDatabaseCount('invitations', 0);
    }

    public function test_expired_tampered_and_old_email_links_cannot_verify_or_sign_in(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($expired)->assertForbidden();
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($link.'tampered')->assertForbidden();
        $user->update(['email' => 'changed@example.test']);
        $this->get($link)->assertForbidden();
        $this->assertFalse($user->refresh()->hasVerifiedEmail());
        $this->assertGuest();
    }

    public function test_resend_requires_proven_pending_session_and_cannot_target_another_user(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $this->post('/email/verification-notification', ['id' => $user->id, 'email' => $user->email])->assertRedirect('/login');
        Notification::assertNothingSent();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/email/verify');
        $this->from('/email/verify')->post('/email/verification-notification', ['id' => $other->id, 'email' => $other->email])->assertRedirect('/email/verify');
        Notification::assertSentToTimes($user, VerifyEmailAddress::class, 1);
        Notification::assertNotSentTo($other, VerifyEmailAddress::class);
        $this->travel(61)->minutes();
        $this->post('/email/verification-notification')->assertRedirect('/login');
        Notification::assertSentToTimes($user, VerifyEmailAddress::class, 1);
    }

    public function test_invitation_destination_survives_registration_verification_and_password_login(): void
    {
        Notification::fake();
        $destination = url('/invite/fictitious-invitation-token');
        $this->withSession(['url.intended' => $destination])->post('/register', ['first_name' => 'Ana', 'last_name' => 'Exemplu', 'email' => 'ana@example.test', 'password' => 'Molid!Vapor7Nori$42', 'password_confirmation' => 'Molid!Vapor7Nori$42', 'terms_accepted' => true, 'legal_version' => config('legal.version')])->assertRedirect('/email/verify')->assertSessionHas('url.intended', $destination);
        $this->assertGuest();
        $user = User::firstOrFail();
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($link)->assertRedirect('/login')->assertSessionHas('url.intended', $destination);
        $this->post('/login', ['email' => $user->email, 'password' => 'Molid!Vapor7Nori$42'])->assertRedirect($destination);
        $this->assertAuthenticatedAs($user);
    }
}
