<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('backup_type')->index();
            $table->string('cron_expression');
            $table->unsignedInteger('retention_count')->default(7);
            $table->unsignedInteger('retention_days')->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->json('configuration')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};
