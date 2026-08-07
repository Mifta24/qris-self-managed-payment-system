<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel inti sistem. Beberapa keputusan yang sengaja diambil:
 *
 * 1. Semua nominal disimpan sebagai BIGINT rupiah penuh, bukan decimal/float.
 *
 * 2. `payment_amount` disimpan (bukan dihitung on the fly) karena nilainya
 *    ikut dikunci oleh unique index dan dipakai admin saat mencocokkan mutasi.
 *
 * 3. Unique parsial pada `payment_amount` untuk invoice yang masih aktif.
 *    Ini pengaman terpenting di seluruh sistem: tanpa ini, dua invoice aktif
 *    bisa punya total sama dan admin berpotensi menandai invoice yang salah
 *    sebagai lunas.
 *
 * 4. `paid_at` != `confirmed_at`.
 *    - paid_at      : waktu pembayaran menurut GoPay Merchant (diinput admin)
 *    - confirmed_at : waktu admin menekan tombol konfirmasi
 *    Dibutuhkan untuk rekonsiliasi dan audit yang jujur.
 *
 * 5. `callback_url` di-snapshot per invoice, tidak diambil dari project saat
 *    dikirim. Kalau URL project berubah, callback invoice lama tetap benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_number', 40)->unique();  // INV-20260806-0001
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('qris_merchant_id')->constrained()->restrictOnDelete();

            // Idempotency: satu external_id hanya boleh menghasilkan satu invoice.
            $table->string('external_id', 100);

            $table->string('description')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 32)->nullable();

            $table->unsignedBigInteger('base_amount');
            $table->unsignedSmallInteger('unique_code')->default(0);
            $table->unsignedBigInteger('payment_amount');
            $table->char('currency', 3)->default('360');

            // Payload QRIS dinamis hasil generator. Gambar QR TIDAK disimpan
            // sebagai file; dirender on the fly dari payload ini lalu di-cache.
            $table->text('qris_payload');

            $table->string('status', 30)->default('created');

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Data verifikasi manual oleh admin
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmation_reference', 100)->nullable(); // RRN / nomor referensi
            $table->text('confirmation_note')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->string('callback_url')->nullable();
            $table->timestamp('callback_delivered_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'external_id']);
            $table->index(['status', 'expires_at']);        // untuk scheduler expiry
            $table->index(['project_id', 'created_at']);
            $table->index('confirmation_reference');
        });

        // --- Kunci nominal untuk invoice aktif ---------------------------------
        // PostgreSQL: partial unique index.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX invoices_active_payment_amount_unique
                ON invoices (qris_merchant_id, payment_amount)
                WHERE status IN ('created', 'waiting_payment', 'manual_verification')
            SQL);
        }

        // SQLite (hanya dipakai untuk dev/test lokal, produksi tetap PostgreSQL):
        // partial index-nya mendukung sintaks WHERE yang sama seperti Postgres.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX invoices_active_payment_amount_unique
                ON invoices (qris_merchant_id, payment_amount)
                WHERE status IN ('created', 'waiting_payment', 'manual_verification')
            SQL);
        }

        // MySQL 8 tidak punya partial index. Padanannya: generated column yang
        // bernilai NULL saat invoice tidak aktif (NULL tidak ikut unique check).
        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE invoices
                ADD COLUMN active_amount_lock BIGINT
                GENERATED ALWAYS AS (
                    CASE WHEN status IN ('created', 'waiting_payment', 'manual_verification')
                         THEN payment_amount
                         ELSE NULL END
                ) STORED,
                ADD COLUMN active_merchant_lock BIGINT
                GENERATED ALWAYS AS (
                    CASE WHEN status IN ('created', 'waiting_payment', 'manual_verification')
                         THEN qris_merchant_id
                         ELSE NULL END
                ) STORED,
                ADD UNIQUE KEY invoices_active_payment_amount_unique
                    (active_merchant_lock, active_amount_lock)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
