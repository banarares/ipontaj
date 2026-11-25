<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use DirectoryIterator;
use Illuminate\Console\Command;

class PruneApplicationLogsCommand extends Command
{
    protected $signature = 'pontaj:logs:prune {--apply : Șterge numai logurile zilnice expirate; implicit doar previzualizează}';

    protected $description = 'Aplică retenția aprobată pentru logurile zilnice locale, fără acces la baza de date';

    public function handle(): int
    {
        $days = (int) config('legal.retention.log_days');
        if ($days < 1) {
            $this->error('Configurează LEGAL_LOG_RETENTION_DAYS cu durata aprobată înainte de curățare.');

            return self::FAILURE;
        }
        if ($this->option('apply') && config('logging.default') !== 'daily') {
            $this->error('Activează LOG_CHANNEL=daily înainte de curățarea automată.');

            return self::FAILURE;
        }
        $path = (string) config('logging.channels.daily.path');
        $directory = realpath(dirname($path));
        $root = realpath(storage_path('logs'));
        if (! $root || is_link(storage_path('logs')) || ! $directory || ($directory !== $root && ! str_starts_with($directory, $root.DIRECTORY_SEPARATOR)) || pathinfo($path, PATHINFO_EXTENSION) !== 'log') {
            $this->error('Curățarea este limitată la fișierele .log din storage/logs, fără directoare externe.');

            return self::FAILURE;
        }
        $pattern = '/^'.preg_quote(pathinfo($path, PATHINFO_FILENAME), '/').'-(\d{4}-\d{2}-\d{2})\.log$/D';
        $timezone = (string) config('app.timezone');
        $cutoff = CarbonImmutable::now($timezone)->startOfDay()->subDays($days);
        $eligible = $deleted = $failed = 0;
        foreach (new DirectoryIterator($directory) as $file) {
            if ($file->isLink() || ! $file->isFile() || ! preg_match($pattern, $file->getFilename(), $match)) {
                continue;
            }
            try {
                $date = CarbonImmutable::createFromFormat('!Y-m-d', $match[1], $timezone);
            } catch (\Throwable) {
                continue;
            }
            if (! $date || $date->format('Y-m-d') !== $match[1] || $date >= $cutoff) {
                continue;
            }
            $eligible++;
            if ($this->option('apply')) {
                if (@unlink($file->getPathname())) {
                    $deleted++;
                } else {
                    $failed++;
                }
            }
        }
        $this->line(json_encode(['retention_days' => $days, 'dry_run' => ! $this->option('apply'), 'eligible' => $eligible, 'deleted' => $deleted, 'failed' => $failed, 'legacy_log_present' => is_file($path)], JSON_THROW_ON_ERROR));
        if (is_file($path)) {
            $this->warn('Logul single anterior nu este șters automat. Revizuiește-l și aplică separat politica aprobată.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
