<?php

declare(strict_types=1);

namespace App\Support\Qris;

use App\Support\Qris\Exceptions\InvalidQrisPayloadException;

/**
 * Satu elemen TLV (Tag-Length-Value) dalam payload QRIS.
 *
 * Format: 2 digit tag + 2 digit panjang (desimal) + value.
 * Karena panjang hanya 2 digit desimal, value maksimal 99 karakter.
 *
 * Node bisa punya anak (template), misalnya tag 26 yang isinya TLV lagi.
 * Saat node punya anak, value diturunkan dari anak-anaknya supaya perubahan
 * di level anak otomatis terbawa ke atas.
 */
final class TlvNode
{
    /** @var array<int, TlvNode>|null */
    private ?array $children;

    /**
     * @param  array<int, TlvNode>|null  $children
     */
    public function __construct(
        public readonly string $tag,
        private string $rawValue,
        ?array $children = null,
    ) {
        $this->children = $children;
    }

    public function value(): string
    {
        if ($this->children === null) {
            return $this->rawValue;
        }

        return implode('', array_map(
            static fn (TlvNode $child): string => $child->toString(),
            $this->children,
        ));
    }

    public function setValue(string $value): void
    {
        $this->rawValue = $value;
        $this->children = null;
    }

    public function hasChildren(): bool
    {
        return $this->children !== null;
    }

    /**
     * @return array<int, TlvNode>
     */
    public function children(): array
    {
        return $this->children ?? [];
    }

    public function child(string $tag): ?TlvNode
    {
        foreach ($this->children ?? [] as $child) {
            if ($child->tag === $tag) {
                return $child;
            }
        }

        return null;
    }

    public function toString(): string
    {
        $value = $this->value();
        $length = strlen($value);

        if ($length > 99) {
            throw InvalidQrisPayloadException::valueTooLong($this->tag, $length);
        }

        return $this->tag
            .str_pad((string) $length, 2, '0', STR_PAD_LEFT)
            .$value;
    }
}
