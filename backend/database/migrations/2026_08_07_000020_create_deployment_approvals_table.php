<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deployment_id')->constrained()->cascadeOnDelete();
            $table->boolean('required_by_policy')->default(true);
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->index();
            $table->text('reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('approval_fingerprint')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_approvals');
    }
};
