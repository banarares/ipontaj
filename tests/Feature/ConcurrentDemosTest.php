<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentDemosTest extends TestCase
{
    public function test_simultaneous_visitors_cannot_exceed_the_demo_capacity(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ipontaj-demo-race-');
        config(['database.default' => 'demo_race', 'database.connections.demo_race' => ['driver' => 'sqlite', 'database' => $path, 'foreign_key_constraints' => true, 'busy_timeout' => 5000]]);
        try {
            Artisan::call('migrate', ['--database' => 'demo_race', '--force' => true]);
            $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $path, 'DB_URL' => '', 'CACHE_STORE' => 'file', 'CACHE_PREFIX' => 'demo-race-'.Str::random(16), 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync'];
            $at = (string) (microtime(true) + 1);
            $a = new Process([PHP_BINARY, base_path('tests/Support/demo-race.php'), $at], base_path(), $env);
            $b = new Process([PHP_BINARY, base_path('tests/Support/demo-race.php'), $at], base_path(), $env);
            $a->start();
            $b->start();
            $a->wait();
            $b->wait();
            $this->assertTrue($a->isSuccessful(), $a->getErrorOutput());
            $this->assertTrue($b->isSuccessful(), $b->getErrorOutput());
            $results = [$a->getOutput(), $b->getOutput()];
            sort($results);
            $this->assertSame(['created', 'limited'], $results);
            $this->assertDatabaseCount('companies', 1);
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('memberships', 4);
        } finally {
            DB::disconnect('demo_race');
            foreach ([$path, $path.'-journal', $path.'-wal', $path.'-shm'] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}
