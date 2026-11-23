<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diisi penjual saat mendaftar sendiri di /toko/register (nama toko dan
 * kontak), supaya admin tahu siapa yang mendaftar sebelum
 * menghubungkannya ke toko. Tidak dipakai untuk keputusan akses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('registration_note')->nullable()->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('registration_note');
        });
    }
};
