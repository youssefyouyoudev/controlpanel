<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->unsignedSmallInteger('expected_status')->default(200);
            $table->string('expected_text')->nullable();
            $table->unsignedSmallInteger('timeout_seconds')->default(5);
            $table->boolean('allow_internal')->default(false);
            $table->string('status')->default('unknown')->index();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('tls_metadata')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_health_checks');
    }
};
