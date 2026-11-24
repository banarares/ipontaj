<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('month_closures', fn (Blueprint $table) => $table->unsignedInteger('approval_version')->default(0));
    }

    public function down(): void
    {
        Schema::table('month_closures', fn (Blueprint $table) => $table->dropColumn('approval_version'));
    }
};
