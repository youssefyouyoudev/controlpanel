<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_executions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action_key')->index();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('queued')->index();
            $table->string('risk_level')->index();
            $table->json('request_options')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('exit_code')->nullable();
            $table->string('summary')->nullable();
            $table->string('output_storage_path', 2048)->nullable();
            $table->text('output_preview')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('request_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'status']);
            $table->index(['website_component_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_executions');
    }
};
