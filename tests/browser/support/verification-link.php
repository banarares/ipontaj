<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\URL;

// CLI-only helper: exercise real signed links in an isolated browser test database.
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$email = $argv[1] ?? '';
$database = realpath(config('database.connections.sqlite.database'));
if (PHP_SAPI !== 'cli' || ! app()->environment('local', 'testing') || config('database.default') !== 'sqlite' || ! $database || ! str_starts_with($database, base_path('.runtime').DIRECTORY_SEPARATOR) || ! str_ends_with($email, '@example.test')) {
    exit(2);
}
$user = User::where('email', $email)->firstOrFail();
echo URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
