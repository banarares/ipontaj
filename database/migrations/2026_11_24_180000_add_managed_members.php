<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->foreignId('managed_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('managed_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('memberships')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Păstrează migrarea: există colegi fără cont ale căror date nu trebuie pierdute.');
        }
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('managed_confirmed_by');
            $table->dropColumn(['first_name', 'last_name', 'managed_confirmed_at']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
