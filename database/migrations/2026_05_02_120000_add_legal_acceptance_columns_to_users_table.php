<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('accepted_terms_at')->nullable()->after('email_verified_at');
            $table->timestamp('accepted_privacy_at')->nullable()->after('accepted_terms_at');
            $table->string('accepted_terms_version', 32)->nullable()->after('accepted_privacy_at');
            $table->string('accepted_privacy_version', 32)->nullable()->after('accepted_terms_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'accepted_terms_at',
                'accepted_privacy_at',
                'accepted_terms_version',
                'accepted_privacy_version',
            ]);
        });
    }
};
