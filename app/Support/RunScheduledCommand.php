<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class RunScheduledCommand
{
    /** @param array<string, bool|int|string> $parameters */
    public function __construct(private string $command, private array $parameters = []) {}

    public function __invoke(): void
    {
        $exit = Artisan::call($this->command, $this->parameters);
        if ($exit !== 0) {
            throw new RuntimeException('Scheduled command '.$this->command.' failed with exit code '.$exit.'.');
        }
    }
}
