<?php

namespace App\Services;

use App\Models\GlobalCustomer;
use Illuminate\Support\Facades\Log;

class GlobalCustomerService
{
    private string $pepper;

    public function __construct()
    {
        // Use app key as pepper for additional security
        $this->pepper = config('app.key', '');
    }

    /**
     * Normalize phone number by stripping non-digits and handling basic prefixes
     * 
     * @param string|null $phone
     * @return string
     */
    private function normalizePhone(?string $phone): string
    {
        if (!$phone) {
            return '';
        }

        // Strip all non-digit characters
        $digits = preg_replace('/\D+/', '', $phone);

        // Handle 00 prefix → + (very basic; replace with libphonenumber if you later add the package)
        if (str_starts_with($digits, '00')) {
            $digits = '+' . substr($digits, 2);
        }

        // If digits exist and don't start with +, keep as-is to avoid guessing country
        // (upgrade later with libphonenumber to E.164 format)
        return $digits;
    }

    /**
     * Generate a hashed fingerprint from email and phone
     * Uses separator to avoid collisions and server-side pepper for additional security
     * 
     * @param string|null $email
     * @param string|null $phone
     * @return string
     */
    public function generateFingerprint(?string $email, ?string $phone): string
    {
        $email = $email ? strtolower(trim($email)) : '';
        $phone = $this->normalizePhone($phone);
        
        // Use delimiter to avoid collisions (e.g., 'ab','c' vs 'a','bc')
        // Add server-side pepper for additional security
        return hash('sha256', $this->pepper . '|' . $email . '|' . $phone);
    }

    /**
     * Find or create a global customer based on email and phone
     * Race-safe using updateOrCreate to avoid duplicate key errors
     * 
     * @param string|null $email
     * @param string|null $phone
     * @return GlobalCustomer
     * @throws \InvalidArgumentException If both email and phone are empty
     */
    public function findOrCreateGlobalCustomer(?string $email, ?string $phone): GlobalCustomer
    {
        $email = $email ? strtolower(trim($email)) : '';
        $phone = $this->normalizePhone($phone);
        
        // Protect against empty identifiers - don't link all "unknown" customers to same GlobalCustomer
        if ($email === '' && $phone === '') {
            throw new \InvalidArgumentException('At least one of email or phone is required to link a global customer.');
        }
        
        $fingerprint = $this->generateFingerprint($email, $phone);
        
        // Use updateOrCreate to avoid race conditions (two requests creating same row)
        GlobalCustomer::query()->updateOrCreate(
            ['hashed_fingerprint' => $fingerprint],
            [
                'primary_email' => $email ?: null,
                'primary_phone' => $phone ?: null,
            ]
        );
        
        // Retrieve the record (guaranteed to exist after updateOrCreate)
        $globalCustomer = GlobalCustomer::where('hashed_fingerprint', $fingerprint)->firstOrFail();
        
        Log::info('Found or created global customer', [
            'global_customer_id' => $globalCustomer->id,
            'has_fingerprint' => !empty($fingerprint),
        ]);
        
        return $globalCustomer;
    }

    /**
     * Find global customer by fingerprint
     * 
     * @param string|null $email
     * @param string|null $phone
     * @return GlobalCustomer|null
     */
    public function findGlobalCustomer(?string $email, ?string $phone): ?GlobalCustomer
    {
        $fingerprint = $this->generateFingerprint($email, $phone);
        return GlobalCustomer::where('hashed_fingerprint', $fingerprint)->first();
    }
}

