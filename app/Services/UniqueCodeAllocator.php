<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NoAvailableAmountException;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Mengalokasikan kode unik lewat unique constraint database, bukan SELECT
 * lebih dulu. Lihat aturan mutlak #3 di CLAUDE.md.
 *
 * Setiap kode dalam rentang project dicoba tepat sekali, dalam urutan acak.
 * Kalau attempt gagal karena bentrok pada `payment_amount` (index
 * `invoices_active_payment_amount_unique`), lanjut ke kode berikutnya. Error
 * database lain langsung dilempar ulang — jangan ditelan.
 */
final class UniqueCodeAllocator
{
    /**
     * @param  callable(int, int): Invoice  $persist  Menerima (uniqueCode, paymentAmount), mengembalikan Invoice yang sudah disimpan.
     */
    public function allocate(Project $project, int $baseAmount, callable $persist): Invoice
    {
        $codes = range($project->unique_code_min, $project->unique_code_max);
        shuffle($codes);

        foreach ($codes as $code) {
            try {
                return DB::transaction(fn (): Invoice => $persist($code, $baseAmount + $code));
            } catch (QueryException $e) {
                if (! $this->isPaymentAmountCollision($e)) {
                    throw $e;
                }
            }
        }

        throw NoAvailableAmountException::rangeExhausted(
            $project->id,
            $project->unique_code_min,
            $project->unique_code_max,
        );
    }

    /**
     * Dicek dari `errorInfo` (pesan mentah driver), bukan `getMessage()` —
     * `getMessage()` selalu menyertakan SQL lengkap, dan "payment_amount"
     * pasti muncul di situ untuk setiap INSERT invoice apa pun konstrain
     * yang bentrok. `errorInfo[2]` hanya berisi pesan asli dari database,
     * jadi kemunculan "payment_amount" di situ benar-benar berarti index
     * `invoices_active_payment_amount_unique` yang bentrok, bukan constraint
     * lain seperti invoice_number atau (project_id, external_id).
     */
    private function isPaymentAmountCollision(QueryException $e): bool
    {
        $driverMessage = $e->errorInfo[2] ?? '';

        return str_contains($driverMessage, 'payment_amount');
    }
}
