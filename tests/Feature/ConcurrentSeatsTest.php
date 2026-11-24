<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentSeatsTest extends TestCase
{
    public static function writers(): array
    {
        return ['two invitations' => ['invitation'], 'invitation and managed colleague' => ['managed']];
    }

    #[DataProvider('writers')]
    public function test_simultaneous_writers_cannot_take_the_last_seat_twice(string $secondWriter): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ipontaj-race-');
        config(['database.default' => 'race', 'database.connections.race' => ['driver' => 'sqlite', 'database' => $path, 'foreign_key_constraints' => true, 'busy_timeout' => 5000]]);
        try {
            Artisan::call('migrate', ['--database' => 'race', '--force' => true]);
            config(['ipontaj.billing_enabled' => true]);
            $owner = User::factory()->create();
            $company = Company::create(['name' => 'Concurență fictivă']);
            $company->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'joined_on' => '2026-11-01']);
            for ($i = 0; $i < 3; $i++) {
                app(TeamService::class)->invite($company, ['first_name' => 'Colegul', 'last_name' => 'fictiv', 'email' => "existing$i@example.test"]);
            }
            $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $path, 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array'];
            $time = (string) (microtime(true) + 1);
            $a = new Process([PHP_BINARY, base_path('tests/Support/invite-race.php'), (string) $company->id, 'a@example.test', $time], base_path(), $env);
            $b = new Process([PHP_BINARY, base_path('tests/Support/invite-race.php'), (string) $company->id, $secondWriter === 'managed' ? (string) $owner->id : 'b@example.test', $time, $secondWriter], base_path(), $env);
            $a->start();
            $b->start();
            $a->wait();
            $b->wait();
            $this->assertTrue($a->isSuccessful(), $a->getErrorOutput());
            $this->assertTrue($b->isSuccessful(), $b->getErrorOutput());
            $results = [$a->getOutput(), $b->getOutput()];
            sort($results);
            $this->assertSame(['accepted', 'limited'], $results);
            $this->assertSame(5, $company->occupiedSeats());
        } finally {
            DB::disconnect('race');
            foreach ([$path, $path.'-journal', $path.'-wal', $path.'-shm'] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}
