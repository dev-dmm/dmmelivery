<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasUuids, Notifiable, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name', // Add name to fillable for compatibility
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'role',
        'permissions',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'permissions', // Internal permissions array
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'last_login' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the current tenant for this user
     */
    public function currentTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function getNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Handle setting the name attribute by splitting it into first and last name
     */
    public function setNameAttribute($value)
    {
        $nameParts = explode(' ', $value, 2);
        $this->attributes['first_name'] = $nameParts[0];
        $this->attributes['last_name'] = $nameParts[1] ?? '';
    }

    /**
     * Role-based helper methods
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->isSuperAdmin();
    }

    public function isUser(): bool
    {
        return $this->hasRole('user');
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /**
     * Get all available roles
     */
    public static function getAvailableRoles(): array
    {
        return [
            'user' => 'User',
            'admin' => 'Admin',
            'super_admin' => 'Super Admin'
        ];
    }
}