<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris per project yang boleh memakai QRIS Payment API
 * (website restoran, sistem reservasi, toko, dsb).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();   // dipakai sebagai project_id di request API
            $table->string('name');
            $table->foreignId('qris_merchant_id')->constrained()->restrictOnDelete();

            $table->string('callback_url')->nullable();
            $table->boolean('is_active')->default(true);

            // Konfigurasi invoice per project
            $table->unsignedSmallInteger('invoice_ttl_minutes')->default(30);
            $table->unsignedSmallInteger('unique_code_min')->default(1);
            $table->unsignedSmallInteger('unique_code_max')->default(999);

            // Kanal notifikasi khusus project ini (telegram chat id, email, dsb).
            // Null = pakai konfigurasi global.
            $table->json('notification_config')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
