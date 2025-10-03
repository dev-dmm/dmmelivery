<?php
/**
 * ELTA Hellenic Post Courier Provider
 * 
 * @package DMM\Courier
 */

namespace DMM\Courier;

class EltaProvider implements Provider {
    /**
     * Get provider ID
     */
    public function id(): string {
        return 'elta';
    }

    /**
     * Get provider label
     */
    public function label(): string {
        return 'ELTA Hellenic Post';
    }

    /**
     * Check if voucher looks like ELTA format
     */
    public function looksLike(string $voucher): bool {
        $clean = preg_replace('/\D/', '', $voucher);
        
        // ELTA tracking numbers are typically 9-13 digits
        // They often have specific patterns or prefixes
        return preg_match('/^\d{9,13}$/', $clean) && 
               !preg_match('/^(0{9,}|1{9,}|123456789)$/', $clean);
    }

    /**
     * Normalize ELTA voucher
     */
    public function normalize(string $voucher): string {
        return preg_replace('/\D/', '', $voucher);
    }

    /**
     * Validate ELTA voucher
     */
    public function validate(string $voucher, \WC_Order $order): array {
        // Format check
        if (!preg_match('/^\d{9,13}$/', $voucher)) {
            return [false, 'Invalid ELTA voucher format'];
        }

        // Sequential/zeros check
        if (preg_match('/^(0{9,}|1{9,}|123456789)$/', $voucher)) {
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

        return [true, 'Valid ELTA voucher'];
    }

    /**
     * Build ELTA API request
     */
    public function route(array $payload): array {
        return array_merge($payload, [
            'courier' => 'elta',
            'api_endpoint' => 'elta_tracking'
        ]);
    }

    /**
     * Fetch ELTA tracking status
     */
    public function fetchTracking(string $voucher): array {
        // Implement ELTA API call using existing DMM_ELTA_Courier_Service
        try {
            $options = get_option('dmm_delivery_bridge_options', []);
            $service = new \DMM_ELTA_Courier_Service($options);
            return $service->get_tracking_details($voucher);
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'ELTA tracking failed: ' . $e->getMessage()
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
