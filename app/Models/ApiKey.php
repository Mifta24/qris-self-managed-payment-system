<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ApiKey extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'key_prefix',
        'key_hash',
        'signing_secret',
        'allowed_ips',
        'last_used_at',
        'last_used_ip',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = [
        'key_hash',
        'signing_secret',
    ];

    protected function casts(): array
    {
        return [
            'signing_secret' => 'encrypted',
            'allowed_ips' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
