<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allowed_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relative_label')->nullable();
            $table->string('absolute_path', 2048);
            $table->char('absolute_path_hash', 64);
            $table->boolean('is_primary')->default(false);
            $table->boolean('can_read')->default(true);
            $table->boolean('can_write')->default(false);
            $table->boolean('can_upload')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_rename')->default(false);
            $table->boolean('can_move')->default(false);
            $table->boolean('can_copy')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_archive')->default(false);
            $table->boolean('can_extract')->default(false);
            $table->unsignedBigInteger('max_upload_bytes')->nullable();
            $table->json('allowed_extensions')->nullable();
            $table->json('blocked_patterns')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'absolute_path_hash', 'is_active'], 'allowed_paths_unique_active_root');
            $table->index(['website_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allowed_paths');
    }
};
