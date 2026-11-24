<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class SourceArchive
{
    /** @return array<string,string> */
    public function files(): array
    {
        $files = [];
        $add = function (string $relative) use (&$files): void {
            $absolute = base_path($relative);
            $real = realpath($absolute);
            if ($real !== false && is_file($absolute) && ! is_link($absolute) && str_starts_with($real, base_path().DIRECTORY_SEPARATOR)) {
                $files[$relative] = $absolute;
            }
        };
        foreach (['app', 'scripts', 'lang', 'config', 'resources', 'routes', 'tests', 'database/migrations', 'database/factories', 'database/seeders', 'docs', '.github'] as $directory) {
            if (! is_dir(base_path($directory))) {
                continue;
            }
            foreach (File::allFiles(base_path($directory)) as $file) {
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen(base_path()) + 1));
                $notice = str_starts_with($relative, 'resources/legal/third-party/') && preg_match('/^(LICENSE|LICENCE|COPYING|NOTICE|OFL)([.\-_].*)?$/i', $file->getFilename());
                if (str_starts_with($relative, 'docs/screenshots/') || str_contains($relative, '/.env') || (! $notice && ! in_array(strtolower($file->getExtension()), ['php', 'ts', 'vue', 'js', 'mjs', 'json', 'css', 'md', 'txt', 'py', 'svg', 'png', 'yml', 'yaml'], true))) {
                    continue;
                }
                $add($relative);
            }
        }
        foreach (['artisan', 'bootstrap/app.php', 'bootstrap/providers.php', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.ts', 'tsconfig.json', 'phpunit.xml', 'phpstan.neon', 'pint.json', 'components.json', 'chisel.php', 'chisel-paths.php', 'pnpm-workspace.yaml', '.nvmrc', '.editorconfig', '.gitattributes', '.env.example', '.gitignore', 'README.md', 'FUNDING.md', 'LICENSE', 'CONTRIBUTING.md', 'SECURITY.md', 'THIRD_PARTY_NOTICES.md', 'public/.htaccess', 'public/index.php', 'public/sw.js', 'public/manifest.webmanifest', 'public/offline.html', 'public/favicon.svg', 'public/favicon.ico', 'public/apple-touch-icon.png', 'public/robots.txt'] as $file) {
            $add($file);
        }
        foreach (['public/icons', 'public/branding', 'public/legal'] as $directory) {
            if (is_dir(base_path($directory))) {
                foreach (File::allFiles(base_path($directory)) as $file) {
                    if (in_array(strtolower($file->getExtension()), ['png', 'svg', 'ico', 'txt', 'md'], true)) {
                        $add(str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen(base_path()) + 1)));
                    }
                }
            }
        }
        ksort($files);

        return $files;
    }

    public function build(): int
    {
        $destination = config('legal.source_archive');
        File::ensureDirectoryExists(dirname($destination), 0700);
        $temporary = tempnam(dirname($destination), 'source-');
        if ($temporary === false) {
            throw new RuntimeException('Arhiva sursei nu poate fi pregătită.');
        }
        try {
            $zip = new ZipArchive;
            if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Arhiva sursei nu poate fi creată.');
            }
            foreach (['bootstrap/cache', 'storage/app/private', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/testing', 'storage/framework/views', 'storage/logs'] as $directory) {
                $zip->addEmptyDir('ipontaj/'.$directory);
            }
            $manifest = [];
            // Keep the build option, without copying local registry credentials from .npmrc.
            $zip->addFromString('ipontaj/.npmrc', "ignore-scripts=true\n");
            $manifest['.npmrc'] = hash('sha256', "ignore-scripts=true\n");
            foreach ($this->files() as $relative => $path) {
                if (! $zip->addFile($path, 'ipontaj/'.$relative)) {
                    throw new RuntimeException('Un fișier sursă nu poate fi arhivat.');
                }
                $manifest[$relative] = hash_file('sha256', $path);
            }
            $zip->addFromString('ipontaj/SOURCE_MANIFEST.json', json_encode(['generated_at' => now()->toIso8601String(), 'legal_version' => config('legal.version'), 'files' => $manifest], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if (! $zip->close() || ! rename($temporary, $destination)) {
                throw new RuntimeException('Arhiva sursei nu poate fi finalizată.');
            }
            chmod($destination, 0640);

            return count($manifest);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
