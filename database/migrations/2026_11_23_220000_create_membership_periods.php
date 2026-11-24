<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->date('start_on');
            $table->date('end_on')->nullable();
        });
        DB::table('memberships')->orderBy('id')->each(fn ($m) => DB::table('membership_periods')->insert(['membership_id' => $m->id, 'start_on' => $m->joined_on, 'end_on' => $m->left_on]));
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_periods');
    }
};
