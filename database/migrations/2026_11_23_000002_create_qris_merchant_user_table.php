<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menghubungkan akun penjual (users) ke toko (qris_merchants) yang boleh
 * dilihat dan dikonfirmasi pembayarannya di panel /toko. Satu penjual bisa
 * punya beberapa toko, satu toko bisa punya beberapa akun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qris_merchant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qris_merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['qris_merchant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qris_merchant_user');
    }
};
