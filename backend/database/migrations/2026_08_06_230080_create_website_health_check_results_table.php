<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_health_check_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_health_check_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('checked_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_health_check_results');
    }
};
