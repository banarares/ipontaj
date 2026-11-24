<?php

namespace App\Console\Commands;

use App\Services\SourceArchive;
use Illuminate\Console\Command;

class SourceArchiveCommand extends Command
{
    protected $signature = 'pontaj:source:build';

    protected $description = 'Pregătește sursa corespunzătoare pentru AGPL, din directoare permise, fără .env, baze de date sau fișiere runtime';

    public function handle(SourceArchive $source): int
    {
        $count = $source->build();
        $this->info('Arhiva sursei a fost pregătită: '.$count.' fișiere. Este disponibilă la /cod-sursa.');

        return self::SUCCESS;
    }
}
