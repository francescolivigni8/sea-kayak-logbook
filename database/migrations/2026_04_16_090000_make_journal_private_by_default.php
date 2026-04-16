<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('profiles')->update(['is_public' => false]);
        DB::table('paddle_sessions')->update(['is_public' => false]);

        $this->setBooleanDefault('profiles', 'is_public', false);
        $this->setBooleanDefault('paddle_sessions', 'is_public', false);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->setBooleanDefault('profiles', 'is_public', true);
        $this->setBooleanDefault('paddle_sessions', 'is_public', true);
    }

    private function setBooleanDefault(string $table, string $column, bool $default): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $value = $default ? 'true' : 'false';

        match ($driver) {
            'pgsql' => DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT {$value}"),
            'mysql', 'mariadb' => DB::statement("ALTER TABLE {$table} MODIFY {$column} TINYINT(1) NOT NULL DEFAULT ".($default ? '1' : '0')),
            default => null,
        };
    }
};
