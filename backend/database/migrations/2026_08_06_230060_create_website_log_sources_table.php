<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_log_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_component_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type')->index();
            $table->string('absolute_path', 2048)->nullable();
            $table->char('absolute_path_hash', 64)->nullable()->index();
            $table->boolean('download_enabled')->default(false);
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_log_sources');
    }
};
