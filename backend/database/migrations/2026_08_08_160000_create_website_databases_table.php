<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_databases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('driver', 32);
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('database_name');
            $table->string('source_path')->nullable();
            $table->string('status')->default('configured');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'driver', 'host', 'port', 'database_name'], 'website_databases_unique_connection');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_databases');
    }
};
