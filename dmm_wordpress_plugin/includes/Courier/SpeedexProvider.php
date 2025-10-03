<?php
/**
 * Speedex Courier Provider
 * 
 * @package DMM\Courier
 */

namespace DMM\Courier;

class SpeedexProvider implements Provider {
    /**
     * Get provider ID
     */
    public function id(): string {
        return 'speedex';
    }

    /**
     * Get provider label
     */
    public function label(): string {
        return 'SPEEDEX';
    }

    /**
     * Check if voucher looks like Speedex format
     */
    public function looksLike(string $voucher): bool {
        $clean = preg_replace('/\D/', '', $voucher);
        return strlen($clean) >= 10 && strlen($clean) <= 14;
    }

    /**
     * Normalize Speedex voucher
     */
    public function normalize(string $voucher): string {
        return preg_replace('/\D/', '', $voucher);
    }

    /**
     * Validate Speedex voucher
     */
    public function validate(string $voucher, \WC_Order $order): array {
        // Format check
        if (!preg_match('/^\d{10,14}$/', $voucher)) {
            return [false, 'Invalid Speedex voucher format'];
        }

        // Phone number check
        if ($this->looksLikePhone($voucher, $order)) {
            return [false, 'Looks like phone number'];
        }

        // Order number check
        if ($this->looksLikeOrderNumber($voucher, $order)) {
            return [false, 'Looks like order number'];
        }

        return [true, 'Valid Speedex voucher'];
    }

    /**
     * Build Speedex API request
     */
    public function route(array $payload): array {
        return array_merge($payload, [
            'courier' => 'speedex',
            'api_endpoint' => 'speedex_tracking'
        ]);
    }

    /**
     * Fetch Speedex tracking status
     */
    public function fetchTracking(string $voucher): array {
        // Implement Speedex API call
        return [
            'status' => 'pending',
            'message' => 'Speedex tracking not implemented yet'
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