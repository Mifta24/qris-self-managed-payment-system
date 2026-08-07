<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat pengiriman callback ke project.
 *
 * Satu baris per PERCOBAAN, bukan per event. Saat callback gagal dan di-retry,
 * baris baru dibuat dengan event_id yang sama dan attempt bertambah. Tanpa ini,
 * menelusuri "kenapa project X tidak menerima notifikasi" hampir mustahil.
 *
 * `event_id` dikirim di body dan header agar penerima bisa idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('callback_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->uuid('event_id');
            $table->string('event_type', 50);          // invoice.paid, invoice.expired, ...
            $table->string('url');
            $table->unsignedTinyInteger('attempt')->default(1);

            $table->json('request_payload');
            $table->string('request_signature', 128)->nullable();

            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->string('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamp('succeeded_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_id', 'attempt']);
            $table->index(['invoice_id', 'created_at']);
            $table->index('next_retry_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('callback_logs');
    }
};
