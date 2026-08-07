<?php

declare(strict_types=1);

namespace App\Support\Qris;

use App\Support\Qris\Exceptions\InvalidQrisPayloadException;

/**
 * Mengubah payload QRIS statis menjadi payload dengan nominal tetap.
 *
 * Soal tag 01 (Point of Initiation Method):
 * Secara EMVCo, QR bernominal seharusnya ditandai "12" (dinamis). Namun di
 * lapangan sebagian aplikasi bank memvalidasi bahwa QR dinamis berasal dari
 * PJP dan menolak QR yang ditandai dinamis tapi diterbitkan sebagai statis.
 * Karena itu default-nya membiarkan tag 01 apa adanya ("11"), dan mode
 * penandaan dinamis dibuat opt-in supaya bisa diuji per aplikasi pembayar.
 *
 * Soal tag 55/56/57 (tip):
 * Kalau payload statis membawa tip indicator, sebagian aplikasi akan tetap
 * meminta pengguna memasukkan tambahan nominal — merusak seluruh premis
 * pencocokan lewat kode unik. Field ini dibuang secara default.
 */
final class DynamicQrisGenerator
{
    private const MAX_AMOUNT_DIGITS = 13;

    public function __construct(
        private readonly bool $markAsDynamic = false,
        private readonly bool $stripTipFields = true,
        private readonly bool $requireValidSourceCrc = true,
    ) {
    }

    /**
     * @param int $amount Nominal dalam rupiah penuh (bukan sen).
     */
    public function generate(string $staticPayload, int $amount): string
    {
        $payload = QrisPayload::parse($staticPayload);

        if ($this->requireValidSourceCrc && ! $payload->crcIsValid()) {
            throw InvalidQrisPayloadException::invalidCrc();
        }

        $this->guardAmount($amount);
        $this->guardCurrency($payload);

        if ($this->markAsDynamic) {
            $payload->set(QrisPayload::TAG_POINT_OF_INITIATION, QrisPayload::POI_DYNAMIC);
        }

        if ($this->stripTipFields) {
            $payload->remove(QrisPayload::TAG_TIP_INDICATOR);
            $payload->remove(QrisPayload::TAG_TIP_FIXED);
            $payload->remove(QrisPayload::TAG_TIP_PERCENT);
        }

        $payload->set(QrisPayload::TAG_AMOUNT, (string) $amount);

        return $payload->toString();
    }

    /**
     * Memastikan payload statis layak dipakai sebagai sumber, dipanggil saat
     * admin menyimpan QRIS merchant baru di dashboard.
     *
     * @return array{merchant_name: ?string, merchant_city: ?string, currency: ?string}
     */
    public function inspect(string $staticPayload): array
    {
        $payload = QrisPayload::parse($staticPayload);

        if (! $payload->crcIsValid()) {
            throw InvalidQrisPayloadException::invalidCrc();
        }

        if ($payload->has(QrisPayload::TAG_AMOUNT)) {
            throw InvalidQrisPayloadException::amountAlreadyPresent();
        }

        return [
            'merchant_name' => $payload->merchantName(),
            'merchant_city' => $payload->merchantCity(),
            'currency' => $payload->currency(),
        ];
    }

    private function guardAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw InvalidQrisPayloadException::invalidAmount($amount);
        }

        if (strlen((string) $amount) > self::MAX_AMOUNT_DIGITS) {
            throw InvalidQrisPayloadException::amountTooLarge($amount);
        }
    }

    private function guardCurrency(QrisPayload $payload): void
    {
        $currency = $payload->currency();

        if ($currency !== null && $currency !== QrisPayload::CURRENCY_IDR) {
            throw InvalidQrisPayloadException::unsupportedCurrency($currency);
        }
    }
}
