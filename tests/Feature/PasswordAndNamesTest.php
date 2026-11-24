<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordAndNamesTest extends TestCase
{
    use RefreshDatabase;

    public static function weakPasswords(): array
    {
        return [
            'prea scurtă' => ['Mare1!Lac'],
            'fără cifră' => ['Molid!VaporNori$Luna'],
            'fără simbol' => ['MolidVapor7NoriLuna'],
            'doar spații în loc de simbol' => ['Molid Vapor 7 Nori Luna'],
            'fără majusculă' => ['molid!vapor7nori$luna'],
            'fără minusculă' => ['MOLID!VAPOR7NORI$LUNA'],
            'parolă frecventă' => ['Password123456!'],
            'repetiții previzibile' => ['AAAAAAAAaaaaaaa1!'],
            'date personale' => ['AnaPopescu2026!'],
        ];
    }

    #[DataProvider('weakPasswords')]
    public function test_server_rejects_weak_passwords_without_trusting_the_browser(string $password): void
    {
        $this->post('/register', ['first_name' => 'Ana', 'last_name' => 'Popescu', 'email' => 'ana@example.test', 'password' => $password, 'password_confirmation' => $password, 'strength' => 4])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_strong_password_and_separate_compound_names_are_accepted_without_external_requests(): void
    {
        Http::preventStrayRequests();
        $this->post('/register', ['terms_accepted' => true, 'legal_version' => config('legal.version'), 'first_name' => 'Ana Maria', 'last_name' => 'Ștefănescu-Popescu', 'email' => 'ana@example.test', 'password' => 'Molid!Vapor7Nori$42', 'password_confirmation' => 'Molid!Vapor7Nori$42'])->assertSessionHasNoErrors()->assertRedirect('/dashboard');
        $user = User::first();
        $this->assertSame('Ana Maria', $user->first_name);
        $this->assertSame('Ștefănescu-Popescu', $user->last_name);
        $this->assertSame('Ana Maria Ștefănescu-Popescu', $user->name);
        $this->assertTrue(Hash::check('Molid!Vapor7Nori$42', $user->password));
    }

    public function test_combined_name_cannot_replace_the_two_required_fields(): void
    {
        $this->post('/register', ['name' => 'Ana Popescu', 'email' => 'ana@example.test', 'password' => 'Molid!Vapor7Nori$42', 'password_confirmation' => 'Molid!Vapor7Nori$42'])->assertSessionHasErrors(['first_name', 'last_name']);
    }

    public function test_password_confirmation_is_required_and_unicode_length_is_bounded_before_hashing(): void
    {
        $data = ['first_name' => 'Ana', 'last_name' => 'Popescu', 'email' => 'ana@example.test', 'password' => 'Molid!Vapor7Nori$42', 'password_confirmation' => 'Altceva!Foarte7Sigur'];
        $this->post('/register', $data)->assertSessionHasErrors('password');
        $data['password'] = str_repeat('ă', 35).'A1!';
        $data['password_confirmation'] = $data['password'];
        $this->post('/register', $data)->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_reset_and_change_password_apply_the_same_policy(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Password123456!', 'password_confirmation' => 'Password123456!'])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->actingAs($user)->put('/settings/password', ['current_password' => 'password', 'password' => 'Password123456!', 'password_confirmation' => 'Password123456!'])->assertSessionHasErrors('password');
        $this->put('/settings/password', ['current_password' => 'password', 'password' => 'Molid!Vapor7Nori$42', 'password_confirmation' => 'Molid!Vapor7Nori$42'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Molid!Vapor7Nori$42', $user->fresh()->password));
    }

    public function test_profile_and_invitation_store_separate_names(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Firma fictivă']);
        $company->memberships()->create(['user_id' => $user->id, 'role' => 'owner', 'joined_on' => '2026-11-01']);
        $this->actingAs($user)->patch('/settings/profile', ['terms_accepted' => true, 'legal_version' => config('legal.version'), 'first_name' => 'Ana Maria', 'last_name' => 'Popescu', 'email' => $user->email, 'name' => 'Nume ignorat'])->assertSessionHasNoErrors();
        $this->assertSame('Ana Maria Popescu', $user->fresh()->name);
        $this->post('/invitations', ['first_name' => 'Radu Mihai', 'last_name' => 'Ionescu', 'email' => 'radu@example.test'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invitations', ['first_name' => 'Radu Mihai', 'last_name' => 'Ionescu', 'name' => 'Radu Mihai Ionescu']);
        $this->post('/invitations', ['first_name' => 'Radu', 'last_name' => 'Georgescu', 'email' => 'radu@example.test'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invitations', ['first_name' => 'Radu', 'last_name' => 'Georgescu', 'name' => 'Radu Georgescu']);
        $this->assertDatabaseCount('invitations', 1);
    }

    public function test_legacy_migration_preserves_original_names_and_passwords(): void
    {
        $migration = require database_path('migrations/2026_11_24_110000_add_separate_person_names.php');
        $migration->down();
        $hash = Hash::make('parola-veche');
        $id = DB::table('users')->insertGetId(['name' => 'Ana Maria Popescu', 'email' => 'legacy@example.test', 'password' => $hash]);
        $single = DB::table('users')->insertGetId(['name' => 'Prince', 'email' => 'single@example.test', 'password' => $hash]);
        $migration->up();
        $old = DB::table('users')->find($id);
        $this->assertSame('Ana Maria Popescu', $old->name);
        $this->assertSame('Ana', $old->first_name);
        $this->assertSame('Maria Popescu', $old->last_name);
        $this->assertSame($hash, $old->password);
        $this->assertSame('', DB::table('users')->find($single)->last_name);
    }
}
