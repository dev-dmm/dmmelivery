<?php

namespace App\Services\Contracts;

interface SecurityServiceInterface
{
    /**
     * Encrypt sensitive data
     */
    public function encryptSensitiveData(string $data): string;

    /**
     * Decrypt sensitive data
     */
    public function decryptSensitiveData(string $encryptedData): string;

    /**
     * Generate secure API token
     */
    public function generateSecureApiToken(): array;

    /**
     * Validate API token
     */
    public function validateApiToken(string $token, string $hashedToken, \DateTime $expiresAt): bool;

    /**
     * Encrypt tenant credentials
     */
    public function encryptTenantCredentials(\App\Models\Tenant $tenant, array $credentials): void;

    /**
     * Decrypt tenant credentials
     */
    public function decryptTenantCredentials(\App\Models\Tenant $tenant): array;

    /**
     * Log security event
     */
    public function logSecurityEvent(string $event, array $data = []): void;

    /**
     * Detect suspicious activity
     */
    public function detectSuspiciousActivity(\App\Models\User $user, string $action): bool;

    /**
     * Generate secure password
     */
    public function generateSecurePassword(int $length = 16): string;

    /**
     * Validate password strength
     */
    public function validatePasswordStrength(string $password): array;

    /**
     * Sanitize input
     */
    public function sanitizeInput(array $data): array;

    /**
     * Validate API request
     */
    public function validateApiRequest(\Illuminate\Http\Request $request): bool;
}

