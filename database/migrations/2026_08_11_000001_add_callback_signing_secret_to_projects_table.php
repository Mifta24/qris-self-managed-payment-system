<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Secret khusus untuk menandatangani callback KELUAR (X-Qris-Signature),
 * terpisah dari `api_keys.signing_secret` yang menandatangani request
 * MASUK dari project. Satu project bisa punya beberapa API key (termasuk
 * yang sudah di-revoke), jadi tidak ada satu signing_secret milik API key
 * yang bisa dijadikan acuan tunggal untuk callback.
 *
 * Kolom nullable di level DB karena project ini tidak memakai
 * doctrine/dbal, sehingga tidak bisa ->change() kolom existing menjadi
 * NOT NULL setelah backfill. Kewajiban "harus terisi" ditegakkan di level
 * aplikasi (default form Filament saat create, dan SendCallbackJob gagal
 * predictable kalau ternyata kosong).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('callback_signing_secret')->nullable()->after('callback_url');
        });

        DB::table('projects')->select('id')->orderBy('id')->chunkById(100, function ($projects) {
            foreach ($projects as $project) {
                DB::table('projects')->where('id', $project->id)->update([
                    'callback_signing_secret' => Crypt::encryptString(Str::random(64)),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('callback_signing_secret');
        });
    }
};
