<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Filament memfilter notifikasi database lewat operator JSON `data->>'format'`.
 * Operator itu cuma didukung PostgreSQL untuk kolom bertipe json/jsonb, bukan
 * text (stub default Laravel di migration notifications). Tanpa ini, bell
 * icon di topbar admin melempar "operator does not exist: text ->> unknown".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE json USING data::json');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
    }
};
