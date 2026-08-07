<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->index();
            $table->string('framework')->nullable();
            $table->string('root_path');
            $table->string('repository_url')->nullable();
            $table->string('repository_branch')->default('main');
            $table->string('status')->default('unknown')->index();
            $table->string('coolify_uuid')->nullable()->index();
            $table->unsignedInteger('assigned_port')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
