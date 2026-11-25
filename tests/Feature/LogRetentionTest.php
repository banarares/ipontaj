<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class LogRetentionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('logs/retention-test-'.Str::random(16));
        File::makeDirectory($this->directory, 0700, true);
        config(['app.timezone' => 'UTC', 'logging.default' => 'daily', 'logging.channels.daily.path' => $this->directory.'/laravel.log', 'legal.retention.log_days' => 30]);
        $this->travelTo(CarbonImmutable::parse('2026-11-24 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function log(string $name): string
    {
        $path = $this->directory.'/'.$name;
        file_put_contents($path, 'Fictitious test log; no personal data.');

        return $path;
    }

    public function test_preview_never_deletes_and_apply_only_expires_old_matching_daily_files(): void
    {
        $old = $this->log('laravel-2026-10-24.log');
        $boundary = $this->log('laravel-2026-10-25.log');
        $current = $this->log('laravel-2026-11-24.log');
        $future = $this->log('laravel-2026-12-01.log');
        $invalid = $this->log('laravel-2026-02-31.log');
        $legacy = $this->log('laravel.log');
        $other = $this->log('audit-2026-01-01.log');
        $this->artisan('pontaj:logs:prune')->expectsOutputToContain('"eligible":1,"deleted":0')->assertSuccessful();
        $this->assertFileExists($old);
        $this->artisan('pontaj:logs:prune', ['--apply' => true])->expectsOutputToContain('"eligible":1,"deleted":1,"failed":0,"legacy_log_present":true')->assertSuccessful();
        $this->assertFileDoesNotExist($old);
        foreach ([$boundary, $current, $future, $invalid, $legacy, $other] as $path) {
            $this->assertFileExists($path);
        }
    }

    public function test_unconfigured_retention_and_single_logging_refuse_deletion(): void
    {
        $old = $this->log('laravel-2026-01-01.log');
        config(['legal.retention.log_days' => 0]);
        $this->artisan('pontaj:logs:prune', ['--apply' => true])->assertFailed();
        $this->assertFileExists($old);
        config(['legal.retention.log_days' => 30, 'logging.default' => 'single']);
        $this->artisan('pontaj:logs:prune', ['--apply' => true])->assertFailed();
        $this->assertFileExists($old);
    }

    public function test_symlinks_and_directories_outside_storage_logs_are_never_pruned(): void
    {
        $target = $this->log('customer-evidence.txt');
        $link = $this->directory.'/laravel-2026-01-01.log';
        symlink($target, $link);
        $this->artisan('pontaj:logs:prune', ['--apply' => true])->expectsOutputToContain('"eligible":0,"deleted":0')->assertSuccessful();
        $this->assertFileExists($target);
        $this->assertTrue(is_link($link));
        config(['logging.channels.daily.path' => storage_path('framework/testing/laravel.log')]);
        $this->artisan('pontaj:logs:prune', ['--apply' => true])->assertFailed();
        $this->assertFileExists($target);
    }

    public function test_pruning_is_idempotent_and_does_not_require_a_database_connection(): void
    {
        $old = $this->log('laravel-2026-01-01.log');
        config(['database.default' => 'unavailable-retention-test']);
        $this->artisan('pontaj:logs:prune', ['--apply' => true])->assertSuccessful();
        $this->assertFileDoesNotExist($old);
        $this->artisan('pontaj:logs:prune', ['--apply' => true])->expectsOutputToContain('"eligible":0,"deleted":0')->assertSuccessful();
    }
}
