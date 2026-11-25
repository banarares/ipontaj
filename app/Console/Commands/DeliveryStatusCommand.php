<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeliveryStatusCommand extends Command
{
    protected $signature = 'pontaj:delivery:status';

    protected $description = 'Raport sigur despre coadă și configurarea emailului, fără parole sau conținutul mesajelor';

    public function handle(): int
    {
        $this->line(json_encode([
            'queue_connection' => config('queue.default'),
            'pending_jobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
            'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
            'worker_last_started_at' => Cache::get('pontaj:queue:last-started'),
            'worker_last_finished_at' => Cache::get('pontaj:queue:last-finished'),
            'mailer' => config('mail.default'),
            'smtp_host_configured' => filled(config('mail.mailers.smtp.host')),
            'smtp_username_configured' => filled(config('mail.mailers.smtp.username')),
            'smtp_password_configured' => filled(config('mail.mailers.smtp.password')),
            'smtp_port' => config('mail.mailers.smtp.port'),
            'smtp_scheme' => config('mail.mailers.smtp.scheme'),
            'contact_recipient' => config('legal.operator.email'),
            'sends_email' => false,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
