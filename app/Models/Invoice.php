<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'project_id',
        'qris_merchant_id',
        'external_id',
        'description',
        'customer_name',
        'customer_email',
        'customer_phone',
        'base_amount',
        'unique_code',
        'payment_amount',
        'currency',
        'qris_payload',
        'status',
        'expires_at',
        'paid_at',
        'confirmed_by',
        'confirmed_at',
        'confirmation_reference',
        'confirmation_note',
        'cancelled_at',
        'cancellation_reason',
        'callback_url',
        'callback_delivered_at',
        'metadata',
    ];

    /**
     * `qris_payload` tidak pernah ikut ter-serialize. Lihat aturan mutlak #5
     * di CLAUDE.md — payload utama tidak boleh keluar dari server lewat jalur
     * apa pun, termasuk log accidental via toArray()/toJson().
     */
    protected $hidden = [
        'qris_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'base_amount' => 'integer',
            'unique_code' => 'integer',
            'payment_amount' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'callback_delivered_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<QrisMerchant, $this>
     */
    public function qrisMerchant(): BelongsTo
    {
        return $this->belongsTo(QrisMerchant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
