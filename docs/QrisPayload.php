<?php

declare(strict_types=1);

namespace App\Support\Qris;

use App\Support\Qris\Exceptions\InvalidQrisPayloadException;

/**
 * Representasi payload QRIS yang sudah diparse, bisa dibaca dan dimodifikasi.
 *
 * Urutan node dipertahankan sesuai payload asli. Saat menambah tag baru,
 * node disisipkan pada posisi terurut secara numerik — mengikuti konvensi
 * EMVCo dan menghindari parser di sisi aplikasi bank yang rewel soal urutan.
 *
 * Tag 63 (CRC) tidak pernah ikut disimpan sebagai node biasa saat serialisasi;
 * nilainya selalu dihitung ulang di toString() sehingga mustahil menghasilkan
 * payload dengan CRC basi.
 */
final class QrisPayload
{
    public const TAG_PAYLOAD_FORMAT = '00';
    public const TAG_POINT_OF_INITIATION = '01';
    public const TAG_MERCHANT_CATEGORY = '52';
    public const TAG_CURRENCY = '53';
    public const TAG_AMOUNT = '54';
    public const TAG_TIP_INDICATOR = '55';
    public const TAG_TIP_FIXED = '56';
    public const TAG_TIP_PERCENT = '57';
    public const TAG_COUNTRY = '58';
    public const TAG_MERCHANT_NAME = '59';
    public const TAG_MERCHANT_CITY = '60';
    public const TAG_POSTAL_CODE = '61';
    public const TAG_ADDITIONAL_DATA = '62';
    public const TAG_CRC = '63';

    public const POI_STATIC = '11';
    public const POI_DYNAMIC = '12';

    public const CURRENCY_IDR = '360';

    /** @var array<int, TlvNode> */
    private array $nodes;

    /**
     * @param array<int, TlvNode> $nodes
     */
    private function __construct(array $nodes)
    {
        $this->nodes = $nodes;
    }

    public static function parse(string $raw): self
    {
        $raw = trim($raw);

        if ($raw === '') {
            throw InvalidQrisPayloadException::empty();
        }

        return new self(self::parseNodes($raw));
    }

    /**
     * Parsing yang tidak melempar exception — berguna untuk validasi form input.
     */
    public static function tryParse(string $raw): ?self
    {
        try {
            return self::parse($raw);
        } catch (InvalidQrisPayloadException) {
            return null;
        }
    }

    /**
     * @return array<int, TlvNode>
     */
    private static function parseNodes(string $raw): array
    {
        $nodes = [];
        $offset = 0;
        $length = strlen($raw);

        while ($offset < $length) {
            // Butuh minimal 4 karakter untuk tag + panjang.
            if ($offset + 4 > $length) {
                throw InvalidQrisPayloadException::truncated($offset);
            }

            $tag = substr($raw, $offset, 2);
            $lengthField = substr($raw, $offset + 2, 2);

            if (! ctype_digit($tag) || ! ctype_digit($lengthField)) {
                throw InvalidQrisPayloadException::malformedAt($offset);
            }

            $valueLength = (int) $lengthField;
            $valueStart = $offset + 4;

            if ($valueStart + $valueLength > $length) {
                throw InvalidQrisPayloadException::truncated($offset);
            }

            $value = substr($raw, $valueStart, $valueLength);

            $nodes[] = new TlvNode(
                $tag,
                $value,
                self::isTemplateTag($tag) ? self::parseChildrenSafely($value) : null,
            );

            $offset = $valueStart + $valueLength;
        }

        if ($nodes === []) {
            throw InvalidQrisPayloadException::empty();
        }

        return $nodes;
    }

    /**
     * Template kadang berisi data yang bukan TLV valid (implementasi acquirer
     * tidak selalu patuh). Kalau gagal diparse, biarkan sebagai value mentah
     * daripada menolak seluruh payload.
     *
     * @return array<int, TlvNode>|null
     */
    private static function parseChildrenSafely(string $value): ?array
    {
        if ($value === '') {
            return null;
        }

        try {
            return self::parseNodes($value);
        } catch (InvalidQrisPayloadException) {
            return null;
        }
    }

    /**
     * Tag yang isinya TLV bersarang menurut EMVCo:
     * 26-51 (merchant account information), 62 (additional data),
     * 64 (merchant information language), 80-99 (unreserved).
     */
    private static function isTemplateTag(string $tag): bool
    {
        $number = (int) $tag;

        return ($number >= 26 && $number <= 51)
            || $number === 62
            || $number === 64
            || ($number >= 80 && $number <= 99);
    }

    public function get(string $tag): ?TlvNode
    {
        foreach ($this->nodes as $node) {
            if ($node->tag === $tag) {
                return $node;
            }
        }

        return null;
    }

    public function has(string $tag): bool
    {
        return $this->get($tag) !== null;
    }

    public function valueOf(string $tag): ?string
    {
        return $this->get($tag)?->value();
    }

    /**
     * Menetapkan nilai tag. Kalau sudah ada, nilainya diganti di tempat
     * (urutan tidak berubah). Kalau belum ada, disisipkan pada posisi
     * terurut secara numerik.
     */
    public function set(string $tag, string $value): void
    {
        $existing = $this->get($tag);

        if ($existing !== null) {
            $existing->setValue($value);

            return;
        }

        $node = new TlvNode($tag, $value);
        $target = (int) $tag;

        foreach ($this->nodes as $index => $current) {
            if ((int) $current->tag > $target) {
                array_splice($this->nodes, $index, 0, [$node]);

                return;
            }
        }

        $this->nodes[] = $node;
    }

    public function remove(string $tag): void
    {
        $this->nodes = array_values(array_filter(
            $this->nodes,
            static fn (TlvNode $node): bool => $node->tag !== $tag,
        ));
    }

    /**
     * Memeriksa CRC payload asli. Dipakai sebagai gerbang sebelum modifikasi:
     * kalau payload sumber saja sudah rusak, hasil generate pasti salah.
     */
    public function crcIsValid(): bool
    {
        $crcNode = $this->get(self::TAG_CRC);

        if ($crcNode === null) {
            return false;
        }

        return Crc16::matches($this->bodyWithCrcHeader(), $crcNode->value());
    }

    public function pointOfInitiation(): ?string
    {
        return $this->valueOf(self::TAG_POINT_OF_INITIATION);
    }

    public function merchantName(): ?string
    {
        return $this->valueOf(self::TAG_MERCHANT_NAME);
    }

    public function merchantCity(): ?string
    {
        return $this->valueOf(self::TAG_MERCHANT_CITY);
    }

    public function currency(): ?string
    {
        return $this->valueOf(self::TAG_CURRENCY);
    }

    public function amount(): ?string
    {
        return $this->valueOf(self::TAG_AMOUNT);
    }

    /**
     * Semua node selain CRC, ditambah header "6304".
     * CRC dihitung atas string ini — termasuk tag dan panjangnya sendiri.
     */
    private function bodyWithCrcHeader(): string
    {
        $body = '';

        foreach ($this->nodes as $node) {
            if ($node->tag === self::TAG_CRC) {
                continue;
            }

            $body .= $node->toString();
        }

        return $body . self::TAG_CRC . '04';
    }

    public function toString(): string
    {
        $body = $this->bodyWithCrcHeader();

        return $body . Crc16::compute($body);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
