<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log untuk setiap perubahan yang menyentuh uang atau kredensial.
 *
 * Pelaku bisa berupa admin (user_id) ATAU project lewat API (api_key_id),
 * karena itu keduanya nullable dan tidak saling menggantikan.
 *
 * Tabel ini sengaja append-only: tidak ada updated_at, tidak ada soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->morphs('auditable');               // invoices, projects, api_keys, ...
            $table->string('event', 60);               // invoice.confirmed, api_key.revoked, ...

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
