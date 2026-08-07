<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type')->index();
            $table->string('relative_working_directory')->default('');
            $table->string('runtime')->nullable();
            $table->string('process_manager')->nullable();
            $table->string('process_name')->nullable();
            $table->string('build_command_key')->nullable();
            $table->string('start_command_key')->nullable();
            $table->string('health_url')->nullable();
            $table->string('status')->default('unknown')->index();
            $table->json('configuration')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['website_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_components');
    }
};
