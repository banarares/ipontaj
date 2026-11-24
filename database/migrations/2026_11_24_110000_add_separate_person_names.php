<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'invitations'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('first_name')->default('');
                $table->string('last_name')->default('');
            });
            // Keep the original display name intact. Legacy names with several words
            // cannot be separated with certainty; users can correct the proposed fields.
            DB::table($name)->orderBy('id')->chunkById(200, function ($people) use ($name): void {
                foreach ($people as $person) {
                    $parts = preg_split('/\s+/u', trim($person->name), 2) ?: [];
                    DB::table($name)->where('id', $person->id)->update(['first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? '']);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'invitations'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['first_name', 'last_name']));
        }
    }
};
