<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('viewer')->index();
            $table->timestamps();

            $table->unique(['website_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_members');
    }
};
