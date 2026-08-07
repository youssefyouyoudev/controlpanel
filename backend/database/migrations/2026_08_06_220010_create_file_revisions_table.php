<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allowed_path_id')->constrained('allowed_paths')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('relative_path', 2048);
            $table->char('relative_path_hash', 64);
            $table->string('operation')->index();
            $table->unsignedBigInteger('original_size')->nullable();
            $table->unsignedBigInteger('new_size')->nullable();
            $table->string('original_checksum')->nullable();
            $table->string('new_checksum')->nullable();
            $table->string('storage_path', 2048)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['website_id', 'allowed_path_id']);
            $table->index(['relative_path_hash', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_revisions');
    }
};
