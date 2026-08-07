<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('queued')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('storage_disk')->default('local');
            $table->string('storage_path', 2048)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
