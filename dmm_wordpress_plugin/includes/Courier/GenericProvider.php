<?php
/**
 * Generic Courier Provider
 * 
 * @package DMM\Courier
 */

namespace DMM\Courier;

class GenericProvider implements Provider {
    /**
     * Get provider ID
     */
    public function id(): string {
        return 'generic';
    }

    /**
     * Get provider label
     */
    public function label(): string {
        return 'Generic';
    }

    /**
     * Check if voucher looks like generic format
     */
    public function looksLike(string $voucher): bool {
        $clean = preg_replace('/\s+/', '', $voucher);
        return (bool) preg_match('/^[A-Za-z0-9-]{8,20}$/', $clean);
    }

    /**
     * Normalize generic voucher
     */
    public function normalize(string $voucher): string {
        return trim($voucher);
    }

    /**
     * Validate generic voucher
     */
    public function validate(string $voucher, \WC_Order $order): array {
        // Basic format check
        if (!preg_match('/^[A-Za-z0-9-]{8,20}$/', $voucher)) {
            return [false, 'Invalid generic voucher format'];
        }

        // Avoid obvious junk
        if (strlen($voucher) < 8) {
            return [false, 'Voucher too short'];
        }

        if (strlen($voucher) > 20) {
            return [false, 'Voucher too long'];
        }

        return [true, 'Valid generic voucher'];
    }

    /**
     * Build generic API request
     */
    public function route(array $payload): array {
        return array_merge($payload, [
            'courier' => 'generic',
            'api_endpoint' => 'generic_tracking'
        ]);
    }

    /**
     * Fetch generic tracking status
     */
    public function fetchTracking(string $voucher): array {
        // Generic tracking not implemented
        return [
            'status' => 'pending',
            'message' => 'Generic tracking not implemented'
        ];
    }
}