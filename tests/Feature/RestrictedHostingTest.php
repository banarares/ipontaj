<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RestrictedHostingTest extends TestCase
{
    use DatabaseMigrations;

    public function test_notifications_migration_adds_only_missing_table_and_preserves_existing_business_data(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'Existing fixture company']);
        $member = $company->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'joined_on' => '2026-11-01']);
        $entry = $member->entries()->create(['company_id' => $company->id, 'date' => '2026-11-24', 'minutes' => 480]);
        Schema::drop('user_notifications');
        DB::table('migrations')->where('migration', '2026_11_25_000001_create_user_notifications_table')->delete();
        $path = 'database/migrations/2026_11_25_000001_create_user_notifications_table.php';
        $this->artisan('migrate', ['--path' => $path, '--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('user_notifications'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'email' => $owner->email]);
        $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'minutes' => 480]);
        $this->artisan('migrate', ['--path' => $path, '--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('time_entries', 1);
    }

    public function test_scheduler_runs_with_process_functions_disabled_and_expires_only_fixture_logs(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('The test supervisor needs proc_open; the child PHP process explicitly disables it.');
        }
        $directory = storage_path('logs/restricted-hosting-'.Str::random(16));
        File::makeDirectory($directory, 0700, true);
        $database = tempnam(sys_get_temp_dir(), 'ipontaj-restricted-');
        $old = $directory.'/laravel-2026-10-01.log';
        $current = $directory.'/laravel-2026-11-25.log';
        file_put_contents($old, 'Fictitious expired log.');
        file_put_contents($current, 'Fictitious current log.');
        $code = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
config(['logging.default' => 'daily', 'logging.channels.daily.path' => getenv('RESTRICTED_LOG_PATH'), 'legal.retention.log_days' => 30]);
$codes = [];
foreach (['2026-11-25 03:00:00 UTC', '2026-11-25 03:20:00 UTC'] as $at) {
    Illuminate\Support\Facades\Date::setTestNow($at);
    $codes[] = Illuminate\Support\Facades\Artisan::call('schedule:run', ['--no-ansi' => true]);
}
echo json_encode(['proc_open_available' => function_exists('proc_open'), 'codes' => $codes, 'table_present' => Illuminate\Support\Facades\Schema::hasTable('user_notifications'), 'event_types' => array_map(fn ($event) => get_class($event), app(Illuminate\Console\Scheduling\Schedule::class)->events())]);
CODE;
        try {
            $process = new Process([PHP_BINARY, '-d', 'disable_functions=proc_open,exec,popen,passthru,shell_exec,system', '-r', $code], base_path(), [
                'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BILLING_ENABLED' => 'false', 'RESTRICTED_LOG_PATH' => $directory.'/laravel.log',
            ]);
            $process->setTimeout(30)->mustRun();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertFalse($result['proc_open_available']);
            $this->assertSame([0, 0], $result['codes']);
            $this->assertTrue($result['table_present']);
            $this->assertSame(array_fill(0, 3, CallbackEvent::class), $result['event_types']);
            $this->assertFileDoesNotExist($old);
            $this->assertFileExists($current);
        } finally {
            File::deleteDirectory($directory);
            @unlink($database);
            @unlink($database.'-wal');
            @unlink($database.'-shm');
        }
    }
}
