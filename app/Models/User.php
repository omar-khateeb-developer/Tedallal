<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Spatie\Permission\Traits\HasRoles;

#[Fillable(['restaurant_id', 'name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isStaff(): bool
    {
        return $this->hasRole('staff');
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function hasPermission(string $permission): bool
    {
        if ($permission === 'toggle_maintenance') {
            return $this->canToggleMaintenance();
        }

        if ($this->isSuperAdmin() || $this->isAdmin()) {
            return true;
        }
        return $this->hasPermissionTo($permission);
    }

    public function canToggleMaintenance(): bool
    {
        // حساب المالك الرئيسي super@tedallal.test حصراً هو من يملك صلاحية إيقاف/تشغيل الصيانة
        return $this->email === 'super@tedallal.test';
    }

    public function canBypassMaintenance(): bool
    {
        // حساب المالك الرئيسي super@tedallal.test حصراً هو المستثنى من وضع الصيانة
        return $this->email === 'super@tedallal.test';
    }
}
