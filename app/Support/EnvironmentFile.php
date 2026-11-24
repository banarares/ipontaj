<?php

namespace App\Support;

use RuntimeException;

final class EnvironmentFile
{
    /** @param array<string,string> $values */
    public static function update(array $values): void
    {
        $path = base_path('.env');
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Fișierul de configurare nu poate fi citit.');
        }
        foreach ($values as $key => $value) {
            if (! in_array($key, ['BILLING_ENABLED', 'STRIPE_TEAM_PRICE', 'STRIPE_WEBHOOK_SECRET', 'STRIPE_PORTAL_CONFIGURATION', 'APP_URL'], true) || ! preg_match('/^[A-Za-z0-9_:.,\/\-]+$/', $value)) {
                throw new RuntimeException('Valoare de configurare invalidă.');
            }
            $line = $key.'='.$value;
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';
            $contents = preg_match($pattern, $contents) ? preg_replace_callback($pattern, fn () => $line, $contents) : rtrim($contents)."\n".$line."\n";
        }
        $temporary = tempnam(storage_path('app/private'), 'env-');
        if ($temporary === false) {
            throw new RuntimeException('Configurarea nu poate fi salvată.');
        }
        try {
            chmod($temporary, 0600);
            if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! rename($temporary, $path)) {
                throw new RuntimeException('Configurarea nu poate fi salvată.');
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }
}
