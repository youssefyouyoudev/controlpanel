<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminal_sessions', function (Blueprint $table): void {
            $table->timestamp('consumed_at')->nullable()->after('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('terminal_sessions', function (Blueprint $table): void {
            $table->dropColumn('consumed_at');
        });
    }
};
