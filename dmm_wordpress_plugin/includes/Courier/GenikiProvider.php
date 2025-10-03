<?php
/**
 * Geniki Taxidromiki Courier Provider
 * 
 * @package DMM\Courier
 */

namespace DMM\Courier;

class GenikiProvider implements Provider {
    /**
     * Get provider ID
     */
    public function id(): string {
        return 'geniki';
    }

    /**
     * Get provider label
     */
    public function label(): string {
        return 'Geniki Taxidromiki';
    }

    /**
     * Check if voucher looks like Geniki format
     */
    public function looksLike(string $voucher): bool {
        $clean = preg_replace('/\D/', '', $voucher);
        
        // Geniki vouchers are typically 8-12 digits
        // They often start with specific patterns
        return preg_match('/^\d{8,12}$/', $clean) && 
               !preg_match('/^(0{8,}|1{8,}|12345678)$/', $clean);
    }

    /**
     * Normalize Geniki voucher
     */
    public function normalize(string $voucher): string {
        return preg_replace('/\D/', '', $voucher);
    }

    /**
     * Validate Geniki voucher
     */
    public function validate(string $voucher, \WC_Order $order): array {
        // Format check
        if (!preg_match('/^\d{8,12}$/', $voucher)) {
            return [false, 'Invalid Geniki voucher format'];
        }

        // Sequential/zeros check
        if (preg_match('/^(0{8,}|1{8,}|12345678)$/', $voucher)) {
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

        return [true, 'Valid Geniki voucher'];
    }

    /**
     * Build Geniki API request
     */
    public function route(array $payload): array {
        return array_merge($payload, [
            'courier' => 'geniki',
            'api_endpoint' => 'geniki_tracking'
        ]);
    }

    /**
     * Fetch Geniki tracking status
     */
    public function fetchTracking(string $voucher): array {
        // Implement Geniki API call using existing DMM_Geniki_Courier_Service
        try {
            $options = get_option('dmm_delivery_bridge_options', []);
            $service = new \DMM_Geniki_Courier_Service($options);
            return $service->track_voucher($voucher);
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Geniki tracking failed: ' . $e->getMessage()
            ];
        }
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
