<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_action_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_component_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('action_key');
            $table->boolean('is_enabled')->default(true)->index();
            $table->string('custom_label')->nullable();
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'website_component_id', 'action_key'], 'website_action_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_action_assignments');
    }
};
