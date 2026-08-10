<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class QrisMerchant extends Model
{
    protected $fillable = [
        'code',
        'label',
        'static_payload',
        'merchant_name',
        'merchant_city',
        'national_merchant_id',
        'currency',
        'is_active',
        'verified_at',
    ];

    /**
     * static_payload sengaja tidak pernah ikut serialize, sama seperti
     * qris_payload di Invoice — lihat aturan mutlak #5 di CLAUDE.md.
     */
    protected $hidden = [
        'static_payload',
    ];

    protected function casts(): array
    {
        return [
            'static_payload' => 'encrypted',
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
