<?php
// app/Models/Tenant.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tenant extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'name',
        'subdomain',
        'logo_url',
        'branding_config',
        'notification_settings',
        'is_active',
        
        // Business Information
        'business_name',
        'business_type',
        'description',
        'website_url',
        'primary_domain',
        
        // Contact Information
        'contact_name',
        'contact_email',
        'contact_phone',
        
        // Business Address
        'business_address',
        'city',
        'postal_code',
        'country',
        
        // Tax Information
        'vat_number',
        'tax_office',
        
        // Business Settings
        'default_currency',
        'tax_rate',
        'shipping_cost',
        'auto_create_shipments',
        'send_notifications',
        
        // Other API Keys (legacy - now handled by WordPress plugin)
        'courier_api_keys',
        
        // Onboarding & Status
        'onboarding_status',
        'onboarding_started_at',
        'onboarding_completed_at',
        'email_verified_at',
        
        // Subscription & Billing
        'subscription_plan',
        'monthly_shipment_limit',
        'current_month_shipments',
        'billing_cycle_start',
        
        // Features & Customization
        'enabled_features',
        'theme_config',
        'favicon_url',
        
        // API & Integration
        'webhook_urls',
        'api_token',
        'api_token_expires_at',
        'integration_settings',
        
        // Admin
        'admin_notes',
        'onboarding_data',
    ];

    protected $hidden = [
        // API Credentials - never expose these
        'courier_api_keys',
        'api_token',
        'webhook_urls',
        'integration_settings',
        
        // Internal/sensitive data
        'admin_notes',
        'onboarding_data',
        'notification_settings',
    ];

    protected $casts = [
        'branding_config' => 'array',
        'notification_settings' => 'array',
        'courier_api_keys' => 'array',
        'enabled_features' => 'array',
        'theme_config' => 'array',
        'webhook_urls' => 'array',
        'integration_settings' => 'array',
        'onboarding_data' => 'array',
        'is_active' => 'boolean',
        'auto_create_shipments' => 'boolean',
        'send_notifications' => 'boolean',
        'tax_rate' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'onboarding_started_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'billing_cycle_start' => 'date',
        'api_token_expires_at' => 'datetime',
    ];

    // Encrypted fields
    protected $encrypted = [
        'courier_api_keys',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function couriers(): HasMany
    {
        return $this->hasMany(Courier::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Onboarding Status Methods
    public function isOnboardingComplete(): bool
    {
        return $this->onboarding_status === 'active';
    }

    public function getOnboardingProgress(): int
    {
        $steps = [
            'pending' => 0,
            'email_verified' => 15,
            'profile_completed' => 35,
            'payment_setup' => 55,
            'api_configured' => 75,
            'testing' => 90,
            'active' => 100,
        ];

        return $steps[$this->onboarding_status] ?? 0;
    }

    public function getNextOnboardingStep(): ?string
    {
        $steps = [
            'pending' => 'email_verification',
            'email_verified' => 'profile_completion',
            'profile_completed' => 'payment_setup',
            'payment_setup' => 'api_configuration',
            'api_configured' => 'testing',
            'testing' => 'activation',
            'active' => null,
        ];

        return $steps[$this->onboarding_status] ?? null;
    }

    // API Configuration Methods
    // Note: Courier credentials are now managed through the WordPress plugin

    // Subscription & Billing Methods
    public function canCreateShipments(): bool
    {
        if ($this->onboarding_status !== 'active') {
            return false;
        }

        return $this->current_month_shipments < $this->monthly_shipment_limit;
    }

    public function getRemainingShipments(): int
    {
        return max(0, $this->monthly_shipment_limit - $this->current_month_shipments);
    }

    public function incrementShipmentCount(): void
    {
        $this->increment('current_month_shipments');
    }

    // Branding & Customization
    public function getThemeConfig(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->theme_config ?? [];
        }

        return data_get($this->theme_config, $key, $default);
    }

    public function getPrimaryColor(): string
    {
        return $this->getThemeConfig('primary_color', '#3B82F6');
    }

    public function getSecondaryColor(): string
    {
        return $this->getThemeConfig('secondary_color', '#6B7280');
    }

    // Domain Handling
    public function getFullDomain(): string
    {
        if ($this->primary_domain) {
            return $this->primary_domain;
        }

        return $this->subdomain . '.eshoptracker.gr'; // Or your base domain
    }

    public function isCustomDomain(): bool
    {
        return !empty($this->primary_domain);
    }

    // API Token Management
    public function generateApiToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->update([
            'api_token' => hash('sha256', $token),
            'api_token_expires_at' => now()->addYear(),
        ]);

        return $token; // Return the unhashed token for the user
    }

    public function isApiTokenValid(string $token): bool
    {
        if (!$this->api_token || !$this->api_token_expires_at) {
            return false;
        }

        if ($this->api_token_expires_at->isPast()) {
            return false;
        }

        return hash_equals($this->api_token, hash('sha256', $token));
    }

    // Shipment Tracking
    public function getCurrentMonthShipments(): int
    {
        return $this->hasMany(\App\Models\Shipment::class)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    // Feature Management
    public function hasFeature(string $feature): bool
    {
        $features = $this->enabled_features ?? [];
        return in_array($feature, $features);
    }

    public function enableFeature(string $feature): void
    {
        $features = $this->enabled_features ?? [];
        if (!in_array($feature, $features)) {
            $features[] = $feature;
            $this->update(['enabled_features' => $features]);
        }
    }

    public function disableFeature(string $feature): void
    {
        $features = $this->enabled_features ?? [];
        $features = array_values(array_filter($features, fn($f) => $f !== $feature));
        $this->update(['enabled_features' => $features]);
    }

    // Security methods for encrypted fields
    public function setEncryptedAttribute(string $key, string $value): void
    {
        if (in_array($key, $this->encrypted)) {
            $this->attributes[$key] = encrypt($value);
        } else {
            $this->attributes[$key] = $value;
        }
    }

    public function getEncryptedAttribute(string $key): ?string
    {
        if (in_array($key, $this->encrypted) && !empty($this->attributes[$key])) {
            try {
                return decrypt($this->attributes[$key]);
            } catch (\Exception $e) {
                \Log::error('Failed to decrypt tenant field', [
                    'tenant_id' => $this->id,
                    'field' => $key,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }
        
        return $this->attributes[$key] ?? null;
    }

    // Secure credential management
    public function updateCredentials(array $credentials): void
    {
        $updates = [];
        
        foreach ($credentials as $key => $value) {
            if (in_array($key, $this->encrypted)) {
                $updates[$key] = encrypt($value);
            } else {
                $updates[$key] = $value;
            }
        }
        
        $this->update($updates);
        
        \Log::info('Tenant credentials updated', [
            'tenant_id' => $this->id,
            'updated_fields' => array_keys($credentials)
        ]);
    }

    public function getDecryptedCredentials(): array
    {
        $credentials = [];
        
        foreach ($this->encrypted as $field) {
            if (!empty($this->attributes[$field])) {
                try {
                    $credentials[$field] = decrypt($this->attributes[$field]);
                } catch (\Exception $e) {
                    \Log::warning('Failed to decrypt credential', [
                        'tenant_id' => $this->id,
                        'field' => $field
                    ]);
                    $credentials[$field] = null;
                }
            }
        }
        
        return $credentials;
    }
}