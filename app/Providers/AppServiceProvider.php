<?php

namespace App\Providers;

use App\Models\AuditEvent;
use App\Models\Company;
use App\Services\ActivityFeed;
use App\Support\PasswordPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Cashier::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        AuditEvent::created(fn ($event) => app(ActivityFeed::class)->audit($event));
        Cashier::useCustomerModel(Company::class);
        Event::listen(Verified::class, fn ($event) => app(ActivityFeed::class)->personal($event->user, 'Adresa de email a fost confirmată'));
        Event::listen(Login::class, fn ($event) => app(ActivityFeed::class)->personal($event->user, 'Autentificare reușită'));
        Event::listen(PasswordReset::class, fn ($event) => app(ActivityFeed::class)->personal($event->user, 'Parola a fost resetată'));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => Password::min(PasswordPolicy::MIN_LENGTH)
            ->letters()->mixedCase()->numbers()->symbols());

    }
}
