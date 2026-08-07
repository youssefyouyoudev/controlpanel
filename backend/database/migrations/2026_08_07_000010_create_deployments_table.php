<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_component_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coolify_resource_link_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coolify_deployment_uuid')->nullable()->index();
            $table->string('provider', 30);
            $table->string('trigger', 30);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->index();
            $table->string('commit_sha')->nullable();
            $table->text('commit_message')->nullable();
            $table->string('branch')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('deployment_url')->nullable();
            $table->string('logs_storage_path')->nullable();
            $table->text('logs_preview')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('preflight')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'status']);
            $table->index(['coolify_resource_link_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
