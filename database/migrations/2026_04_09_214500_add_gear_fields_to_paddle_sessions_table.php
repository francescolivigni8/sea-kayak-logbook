<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paddle_sessions', function (Blueprint $table) {
            $table->string('kayak_used')->nullable()->after('body_of_water');
            $table->string('paddle_used')->nullable()->after('kayak_used');
        });
    }

    public function down(): void
    {
        Schema::table('paddle_sessions', function (Blueprint $table) {
            $table->dropColumn(['kayak_used', 'paddle_used']);
        });
    }
};
