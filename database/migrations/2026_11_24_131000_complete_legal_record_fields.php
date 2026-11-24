<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_acceptances', function (Blueprint $table): void {
            $table->timestamp('confirmed_at')->nullable();
        });
        Schema::table('legal_requests', function (Blueprint $table): void {
            $table->string('name', 255)->change();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('legal_acceptances', fn (Blueprint $table) => $table->dropColumn('confirmed_at'));
        Schema::table('legal_requests', fn (Blueprint $table) => $table->dropColumn(['first_name', 'last_name']));
    }
};
