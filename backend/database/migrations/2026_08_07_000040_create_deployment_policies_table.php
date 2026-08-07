<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_component_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('environment')->default('production');
            $table->boolean('requires_clean_git')->default(false);
            $table->boolean('requires_backup')->default(false);
            $table->boolean('requires_approval')->default(true);
            $table->json('allowed_branches')->nullable();
            $table->json('protected_branches')->nullable();
            $table->unsignedBigInteger('minimum_free_disk_bytes')->nullable();
            $table->boolean('maintenance_mode')->default(false);
            $table->boolean('health_check_after_deploy')->default(true);
            $table->boolean('auto_rollback_enabled')->default(false);
            $table->unsignedInteger('cooldown_seconds')->default(60);
            $table->unsignedInteger('maximum_concurrent_deployments')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'website_component_id', 'environment'], 'deploy_policy_site_component_env_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_policies');
    }
};
