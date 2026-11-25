<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Output\NullOutput;

class WorkQueueCommand extends Command
{
    protected $signature = 'pontaj:queue:work';

    protected $description = 'Worker limitat pentru cron cPanel, cu lock comun pentru a evita suprapunerea';

    public function handle(): int
    {
        $lock = Cache::lock('pontaj:queue:cron-worker', 180);
        if (! $lock->get()) {
            $this->info('Un worker este deja activ.');

            return self::SUCCESS;
        }
        try {
            Cache::put('pontaj:queue:last-started', now()->toIso8601String(), now()->addDays(2));
            $result = Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 50, '--tries' => 3, '--backoff' => '15', '--timeout' => 45, '--sleep' => 1], new NullOutput);
            Cache::put('pontaj:queue:last-finished', now()->toIso8601String(), now()->addDays(2));
            $this->info('Execuția workerului s-a încheiat. Consultă pontaj:delivery:status pentru coadă și eșuări.');

            return $result;
        } finally {
            $lock->release();
        }
    }
}
