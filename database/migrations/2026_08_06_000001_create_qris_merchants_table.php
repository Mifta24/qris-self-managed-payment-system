<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyimpan payload QRIS statis milik merchant (GoPay Merchant, dll).
 *
 * Alasan dipisah dari .env:
 * - payload bisa dirotasi tanpa deploy ulang;
 * - mendukung lebih dari satu merchant di masa depan;
 * - kolom payload dienkripsi lewat cast `encrypted` di model, sehingga
 *   isi database dump tidak langsung membocorkan QRIS utama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qris_merchants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();          // contoh: gopay-utama
            $table->string('label');                        // contoh: GoPay Merchant - Toko A
            $table->text('static_payload');                 // encrypted cast di model
            $table->string('merchant_name')->nullable();    // hasil parse tag 59
            $table->string('merchant_city', 100)->nullable(); // tag 60
            $table->string('national_merchant_id', 50)->nullable();
            $table->char('currency', 3)->default('360');    // 360 = IDR
            $table->boolean('is_active')->default(true);
            $table->timestamp('verified_at')->nullable();   // terakhir kali payload diuji scan
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qris_merchants');
    }
};
