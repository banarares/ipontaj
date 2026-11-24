<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('customer_type', 16)->default('business');
        });
        Schema::create('legal_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 32);
            $table->string('version', 40);
            $table->json('documents');
            $table->json('facts');
            $table->timestamp('accepted_at');
            $table->index(['user_id', 'purpose', 'version']);
            $table->index(['company_id', 'purpose', 'version']);
        });
        Schema::create('legal_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 24);
            $table->string('name', 200);
            $table->string('email', 254);
            $table->string('contract_reference', 200)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestamp('received_at');
            $table->timestamp('due_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_requests');
        Schema::dropIfExists('legal_acceptances');
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('customer_type'));
    }
};
