<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diisi oleh SendCallbackJob setelah percobaan ke-6 gagal. Ini yang membuat
 * invoice "ditandai butuh perhatian admin di dashboard" sesuai
 * docs/api-contract.md. Dashboard yang menampilkannya adalah item roadmap
 * terpisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('callback_failed_at')->nullable()->after('callback_delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('callback_failed_at');
        });
    }
};
