<?php

namespace App\Services;

use App\Services\Contracts\SecurityServiceInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Tenant;
use App\Models\User;

class SecurityService implements SecurityServiceInterface
{
    /**
     * Encrypt sensitive data
     */
    public function encryptSensitiveData(string $data): string
    {
        try {
            return Crypt::encryptString($data);
        } catch (\Exception $e) {
            Log::error('Failed to encrypt sensitive data', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Encryption failed');
        }
    }

    /**
     * Decrypt sensitive data
     */
    public function decryptSensitiveData(string $encryptedData): string
    {
        try {
            return Crypt::decryptString($encryptedData);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt sensitive data', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Decryption failed');
        }
    }

    /**
     * Generate secure API token
     */
    public function generateSecureApiToken(): array
    {
        $token = Str::random(64);
        $hashedToken = Hash::make($token);
        $expiresAt = now()->addYear();

        return [
            'token' => $token,
            'hashed_token' => $hashedToken,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Validate API token
     */
    public function validateApiToken(string $token, string $hashedToken, \DateTime $expiresAt): bool
    {
        if ($expiresAt < now()) {
            return false;
        }

        return Hash::check($token, $hashedToken);
    }

    /**
     * Encrypt tenant credentials
     */
    public function encryptTenantCredentials(Tenant $tenant, array $credentials): void
    {
        $encryptedCredentials = [];
        
        foreach ($credentials as $key => $value) {
            if (!empty($value)) {
                $encryptedCredentials[$key] = $this->encryptSensitiveData($value);
            }
        }

        $tenant->update($encryptedCredentials);
        
        Log::info('Tenant credentials encrypted', [
            'tenant_id' => $tenant->id,
            'encrypted_fields' => array_keys($encryptedCredentials)
        ]);
    }

    /**
     * Decrypt tenant credentials
     */
    public function decryptTenantCredentials(Tenant $tenant): array
    {
        $credentials = [];
        $encryptedFields = [
            'acs_api_key',
            'acs_company_password', 
            'acs_user_password',
            'courier_api_keys'
        ];

        foreach ($encryptedFields as $field) {
            if (!empty($tenant->$field)) {
                try {
                    $credentials[$field] = $this->decryptSensitiveData($tenant->$field);
                } catch (\Exception $e) {
                    Log::warning('Failed to decrypt tenant credential', [
                        'tenant_id' => $tenant->id,
                        'field' => $field,
                        'error' => $e->getMessage()
                    ]);
                    $credentials[$field] = null;
                }
            }
        }

        return $credentials;
    }

    /**
     * Audit log for security events
     */
    public function logSecurityEvent(string $event, array $data = []): void
    {
        Log::channel('security')->info($event, array_merge($data, [
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()?->tenant_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ]));
    }

    /**
     * Check for suspicious activity
     */
    public function detectSuspiciousActivity(User $user, string $action): bool
    {
        $suspiciousPatterns = [
            'multiple_failed_logins' => $this->checkMultipleFailedLogins($user),
            'unusual_ip' => $this->checkUnusualIp($user),
            'rapid_api_calls' => $this->checkRapidApiCalls($user),
        ];

        $suspicious = array_filter($suspiciousPatterns);

        if (!empty($suspicious)) {
            $this->logSecurityEvent('Suspicious activity detected', [
                'user_id' => $user->id,
                'action' => $action,
                'patterns' => array_keys($suspicious)
            ]);
            return true;
        }

        return false;
    }

    /**
     * Check for multiple failed login attempts
     */
    private function checkMultipleFailedLogins(User $user): bool
    {
        $failedAttempts = \DB::table('failed_jobs')
            ->where('payload', 'like', '%' . $user->email . '%')
            ->where('failed_at', '>=', now()->subMinutes(15))
            ->count();

        return $failedAttempts > 5;
    }

    /**
     * Check for unusual IP address
     */
    private function checkUnusualIp(User $user): bool
    {
        $currentIp = request()->ip();
        $recentIps = \DB::table('activity_log')
            ->where('causer_id', $user->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->pluck('properties->ip_address')
            ->unique()
            ->toArray();

        return !in_array($currentIp, $recentIps) && count($recentIps) > 3;
    }

    /**
     * Check for rapid API calls
     */
    private function checkRapidApiCalls(User $user): bool
    {
        $recentCalls = \DB::table('activity_log')
            ->where('causer_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(1))
            ->count();

        return $recentCalls > 20;
    }

    /**
     * Generate secure password
     */
    public function generateSecurePassword(int $length = 16): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $password;
    }

    /**
     * Validate password strength
     */
    public function validatePasswordStrength(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'strength_score' => $this->calculatePasswordStrength($password)
        ];
    }

    /**
     * Calculate password strength score
     */
    private function calculatePasswordStrength(string $password): int
    {
        $score = 0;
        
        // Length bonus
        $score += min(strlen($password) * 2, 20);
        
        // Character variety bonus
        if (preg_match('/[a-z]/', $password)) $score += 5;
        if (preg_match('/[A-Z]/', $password)) $score += 5;
        if (preg_match('/[0-9]/', $password)) $score += 5;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 10;
        
        // Complexity bonus
        $uniqueChars = count(array_unique(str_split($password)));
        $score += min($uniqueChars * 2, 20);
        
        return min($score, 100);
    }

    /**
     * Sanitize input data
     */
    public function sanitizeInput(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Remove potentially dangerous characters
                $sanitized[$key] = strip_tags(trim($value));
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Validate API request
     */
    public function validateApiRequest(\Illuminate\Http\Request $request): bool
    {
        // Check for required headers
        if (!$request->hasHeader('X-API-Key')) {
            return false;
        }
        
        // Check rate limiting
        $key = 'api_rate_limit:' . $request->ip();
        $attempts = \Cache::get($key, 0);
        
        if ($attempts > 100) { // 100 requests per minute
            return false;
        }
        
        \Cache::put($key, $attempts + 1, 60);
        
        return true;
    }
}
