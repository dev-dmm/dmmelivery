<?php
/**
 * Meta Key to Courier Mapping
 * 
 * @package DMM\Courier
 */

namespace DMM\Courier;

class MetaMapping {
    /**
     * Get voucher meta key mapping
     * 
     * @return array<string,string[]>
     */
    public static function getVoucherMetaMap(): array {
        $map = [
            'acs' => [
                '_acs_voucher',
                'acs_voucher', 
                'acs_tracking',
                '_appsbyb_acs_courier_gr_no_pod',
                '_appsbyb_acs_courier_gr_no_pod_pieces'
            ],
            'geniki' => [
                '_geniki_voucher',
                'geniki_voucher',
                'geniki_tracking',
                '_geniki_tracking',
                'gtx_voucher',
                'gtx_tracking',
                '_gtx_voucher',
                '_gtx_tracking',
                'taxidromiki_voucher',
                'taxidromiki_tracking'
            ],
            'elta' => [
                '_elta_voucher',
                'elta_voucher',
                'elta_tracking',
                '_elta_tracking',
                'elta_reference',
                '_elta_reference',
                'hellenic_post_voucher',
                'hellenic_post_tracking'
            ],
            'speedex' => [
                'obs_speedex_courier',
                'obs_speedex_courier_pieces'
            ],
            'generic' => [
                'voucher_number',
                'tracking_number',
                'shipment_id',
                '_dmm_delivery_shipment_id',
                'courier',
                'shipping_courier',
                'courier_service',
                'courier_company',
                'shipping_provider',
                'delivery_service',
                'shipping_service',
                'transport_method'
            ]
        ];

        return apply_filters('dmm_voucher_meta_keys', $map);
    }

    /**
     * Detect courier from meta key
     */
    public static function detectCourierFromKey(string $meta_key): ?string {
        foreach (self::getVoucherMetaMap() as $courier => $keys) {
            if (in_array($meta_key, $keys, true)) {
                return $courier;
            }
        }
        return null;
    }

    /**
     * Get default courier for unknown keys
     */
    public static function getDefaultCourier(): string {
        return get_option('dmm_default_courier', 'acs');
    }

    /**
     * Get courier priority order
     */
    public static function getCourierPriority(): array {
        $priority = get_option('dmm_courier_priority', ['acs', 'geniki', 'elta', 'speedex', 'generic']);
        return is_array($priority) ? $priority : ['acs', 'geniki', 'elta', 'speedex', 'generic'];
    }

    /**
     * Detect courier from order note
     */
    public static function detectCourierFromNote(string $note_content): ?string {
        // ACS patterns
        if (preg_match('/\b(ACS|ΑCS)\b/i', $note_content)) {
            return 'acs';
        }

        // Geniki patterns
        if (preg_match('/\b(GENIKI|Geniki|geniki|GTX|gtx|TAXIDROMIKI|Taxidromiki|taxidromiki)\b/i', $note_content)) {
            return 'geniki';
        }

        // ELTA patterns
        if (preg_match('/\b(ELTA|elta|HELLENIC POST|Hellenic Post|hellenic post)\b/i', $note_content)) {
            return 'elta';
        }

        // Speedex patterns
        if (preg_match('/\bSPEEDEX\b/i', $note_content)) {
            return 'speedex';
        }

        // Generic patterns
        if (preg_match('/\b(courier|tracking|shipment|voucher)\b/i', $note_content)) {
            return 'generic';
        }

        return null;
    }

    /**
     * Resolve courier by trying all providers in priority order
     */
    public static function resolveByValidators(string $voucher, array $priority_order): ?string {
        foreach ($priority_order as $courier_id) {
            $provider = Registry::get($courier_id);
            if ($provider && $provider->looksLike($voucher)) {
                return $courier_id;
            }
        }
        return null;
    }
}