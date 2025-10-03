<?php
/**
 * ACS Courier Provider
 * 
 * @package DMM\Courier
 */

namespace DMM\Courier;

class AcsProvider implements Provider {
    /**
     * Get provider ID
     */
    public function id(): string {
        return 'acs';
    }

    /**
     * Get provider label
     */
    public function label(): string {
        return 'ACS';
    }

    /**
     * Check if voucher looks like ACS format
     */
    public function looksLike(string $voucher): bool {
        $clean = preg_replace('/\D/', '', $voucher);
        return preg_match('/^\d{10,12}$/', $clean);
    }

    /**
     * Normalize ACS voucher
     */
    public function normalize(string $voucher): string {
        return preg_replace('/\D/', '', $voucher);
    }

    /**
     * Validate ACS voucher
     */
    public function validate(string $voucher, \WC_Order $order): array {
        // Format check
        if (!preg_match('/^(?:00)?\d{10,12}$/', $voucher)) {
            return [false, 'Invalid ACS voucher format'];
        }

        // Sequential/zeros check
        if (preg_match('/^(0{10,}|1{10,}|1234567890)$/', $voucher)) {
            return [false, 'Sequential or zero pattern detected'];
        }

        // Phone number check
        if ($this->looksLikePhone($voucher, $order)) {
            return [false, 'Looks like phone number'];
        }

        // Order number check
        if ($this->looksLikeOrderNumber($voucher, $order)) {
            return [false, 'Looks like order number'];
        }

        return [true, 'Valid ACS voucher'];
    }

    /**
     * Build ACS API request
     */
    public function route(array $payload): array {
        return array_merge($payload, [
            'courier' => 'acs',
            'api_endpoint' => 'acs_tracking'
        ]);
    }

    /**
     * Fetch ACS tracking status
     */
    public function fetchTracking(string $voucher): array {
        // Implement ACS API call
        return [
            'status' => 'pending',
            'message' => 'ACS tracking not implemented yet'
        ];
    }

    /**
     * Check if voucher looks like phone number
     */
    private function looksLikePhone(string $voucher, \WC_Order $order): bool {
        // Check against billing phone
        $billing_phone = $order->get_billing_phone();
        if ($billing_phone && strpos($billing_phone, $voucher) !== false) {
            return true;
        }

        // Check against shipping phone
        $shipping_phone = $order->get_shipping_phone();
        if ($shipping_phone && strpos($shipping_phone, $voucher) !== false) {
            return true;
        }

        return false;
    }

    /**
     * Check if voucher looks like order number
     */
    private function looksLikeOrderNumber(string $voucher, \WC_Order $order): bool {
        $order_id = $order->get_id();
        $order_number = $order->get_order_number();
        
        // Check against order ID
        if ($voucher === (string)$order_id) {
            return true;
        }

        // Check against order number
        if ($order_number && $voucher === $order_number) {
            return true;
        }

        return false;
    }
}