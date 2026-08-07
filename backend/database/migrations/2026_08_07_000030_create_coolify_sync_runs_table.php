<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coolify_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('website_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('resource_type')->nullable();
            $table->string('status', 30)->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('created_links')->default(0);
            $table->unsignedInteger('updated_links')->default(0);
            $table->unsignedInteger('unmatched_resources')->default(0);
            $table->json('errors')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coolify_sync_runs');
    }
};
