<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kredensial API per project.
 *
 * CATATAN PENTING soal penyimpanan:
 * - `key_hash`  -> hash satu arah dari API key. Dipakai untuk autentikasi
 *                  request; cukup dibandingkan, tidak perlu dibaca balik.
 * - `signing_secret` -> HARUS reversible (cast `encrypted`), karena server
 *                  perlu menghitung ulang HMAC dari body request/callback.
 *                  Ini sering salah dikira bisa di-hash seperti password.
 *
 * `key_prefix` disimpan terpisah agar lookup tetap satu query indexed
 * tanpa harus mem-brute-force seluruh tabel saat verifikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('name');                        // label, contoh: "Production - server utama"
            $table->string('key_prefix', 24)->unique();    // contoh: qris_live_9f3a2c
            $table->string('key_hash', 255);               // hash(API key penuh)
            $table->text('signing_secret');                // encrypted cast di model

            $table->json('allowed_ips')->nullable();       // null = tanpa pembatasan IP
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
