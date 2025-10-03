<?php
/**
 * Courier Provider Interface
 * 
 * @package DMM\Courier
 */

namespace DMM\Courier;

interface Provider {
    /**
     * Get provider ID
     */
    public function id(): string;
    
    /**
     * Get provider label
     */
    public function label(): string;
    
    /**
     * Check if voucher looks like this provider's format
     */
    public function looksLike(string $voucher): bool;
    
    /**
     * Normalize voucher format
     */
    public function normalize(string $voucher): string;
    
    /**
     * Validate voucher
     * 
     * @return array [bool $valid, string $reason]
     */
    public function validate(string $voucher, \WC_Order $order): array;
    
    /**
     * Build API request payload
     */
    public function route(array $payload): array;
    
    /**
     * Fetch tracking status (optional)
     */
    public function fetchTracking(string $voucher): array;
}