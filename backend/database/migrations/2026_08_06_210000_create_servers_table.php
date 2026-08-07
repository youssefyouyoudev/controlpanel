<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('hostname');
            $table->text('description')->nullable();
            $table->string('operating_system')->nullable();
            $table->boolean('is_local')->default(false)->index();
            $table->string('status')->default('unknown')->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
