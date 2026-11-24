<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false);
        });
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('timezone')->default('Europe/Bucharest');
            $table->string('plan')->default('free');
            $table->boolean('is_demo')->default(false);
            $table->string('accountant_email')->nullable();
            $table->timestamp('accountant_confirmed_at')->nullable();
            $table->boolean('reminders')->default(true);
            $table->string('stripe_id')->nullable()->unique();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('employee');
            $table->boolean('active')->default(true);
            $table->boolean('tracked')->default(true);
            $table->boolean('reminders')->default(true);
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->json('weekdays');
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->timestamps();
        });
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('month_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'month']);
        });
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('absence')->nullable();
            $table->unsignedInteger('minutes')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['membership_id', 'date']);
        });
        Schema::create('time_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_entry_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('work');
            $table->timestamp('start_at');
            $table->timestamp('end_at');
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
        });
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('key')->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->timestamp('processed_at');
        });
    }

    public function down(): void
    {
        foreach (['webhook_events', 'notification_deliveries', 'audit_events', 'time_intervals', 'time_entries', 'month_closures', 'invitations', 'schedules', 'memberships', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_demo'));
    }
};
