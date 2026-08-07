<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trash_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allowed_path_id')->constrained('allowed_paths')->cascadeOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_relative_path', 2048);
            $table->string('trash_storage_path', 2048);
            $table->string('item_type')->index();
            $table->unsignedBigInteger('original_size')->nullable();
            $table->string('checksum')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('restored_at')->nullable()->index();

            $table->index(['website_id', 'allowed_path_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trash_entries');
    }
};
