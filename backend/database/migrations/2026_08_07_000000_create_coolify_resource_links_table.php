<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coolify_resource_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_component_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('resource_type', 40);
            $table->string('coolify_uuid');
            $table->string('coolify_project_uuid')->nullable();
            $table->string('coolify_environment_uuid')->nullable();
            $table->string('display_name')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_status', 60)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['resource_type', 'coolify_uuid']);
            $table->index(['website_id', 'resource_type', 'is_active']);
            $table->index(['website_component_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coolify_resource_links');
    }
};
