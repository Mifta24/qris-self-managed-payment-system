<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'registration_note'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Panel `admin` hanya untuk admin. Panel `seller` (/toko) hanya untuk
     * akun yang terhubung ke minimal satu toko. `is_admin` sengaja tidak ada
     * di #[Fillable] supaya tidak bisa diubah lewat mass assignment.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->is_admin,
            'seller' => $this->qrisMerchants()->exists(),
            default => false,
        };
    }

    /**
     * @return BelongsToMany<QrisMerchant, $this>
     */
    public function qrisMerchants(): BelongsToMany
    {
        return $this->belongsToMany(QrisMerchant::class)->withTimestamps();
    }

    /**
     * @return Collection<int, QrisMerchant>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->qrisMerchants()->orderBy('label')->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->qrisMerchants()->whereKey($tenant->getKey())->exists();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
