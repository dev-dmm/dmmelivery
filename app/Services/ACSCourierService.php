<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\Shipment;
use App\Services\CacheService;
use App\Exceptions\CourierApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ACSCourierService
{
    private string $apiEndpoint;
    private CacheService $cacheService;

    public function __construct(Courier $courier)
    {
        $this->apiEndpoint = $courier->api_endpoint;
        $this->cacheService = app(CacheService::class);
    }

    /**
     * Get tracking details for a shipment
     * Credentials are now passed from the WordPress plugin
     */
    public function getTrackingDetails(string $voucherNumber, array $credentials = []): array
    {
        // Check cache first
        $cachedResponse = $this->cacheService->getCachedCourierResponse($voucherNumber);
        if ($cachedResponse) {
            Log::info('ACS API: Using cached response', ['voucher_number' => $voucherNumber]);
            return $cachedResponse;
        }

        // Use credentials passed from WordPress plugin
        $payload = [
            'ACSAlias' => 'ACS_TrackingDetails',
            'ACSInputParameters' => [
                'Company_ID' => $credentials['company_id'] ?? '',
                'Company_Password' => $credentials['company_password'] ?? '',
                'User_ID' => $credentials['user_id'] ?? '',
                'User_Password' => $credentials['user_password'] ?? '',
                'Language' => 'GR',
                'Voucher_No' => $voucherNumber
            ]
        ];

        $response = $this->makeApiCall($payload);
        
        // Cache successful responses for 5 minutes
        if ($response['success']) {
            $this->cacheService->cacheCourierResponse($voucherNumber, $response, 300);
        }

        return $response;
    }

    /**
     * Get tracking summary for a shipment
     * Credentials are now passed from the WordPress plugin
     */
    public function getTrackingSummary(string $voucherNumber, array $credentials = []): array
    {
        $payload = [
            'ACSAlias' => 'ACS_Trackingsummary',
            'ACSInputParameters' => [
                'Company_ID' => $credentials['company_id'] ?? '',
                'Company_Password' => $credentials['company_password'] ?? '',
                'User_ID' => $credentials['user_id'] ?? '',
                'User_Password' => $credentials['user_password'] ?? '',
                'Language' => 'GR',
                'Voucher_No' => $voucherNumber
            ]
        ];

        return $this->makeApiCall($payload);
    }

    /**
     * Create a new voucher/shipment
     * Credentials are now passed from the WordPress plugin
     */
    public function createVoucher(array $shipmentData, array $credentials = []): array
    {
        $payload = [
            'ACSAlias' => 'ACS_Create_Voucher',
            'ACSInputParameters' => array_merge([
                'Company_ID' => $credentials['company_id'] ?? '',
                'Company_Password' => $credentials['company_password'] ?? '',
                'User_ID' => $credentials['user_id'] ?? '',
                'User_Password' => $credentials['user_password'] ?? '',
                'Language' => 'GR',
            ], $shipmentData)
        ];

        return $this->makeApiCall($payload);
    }

    /**
     * Calculate shipping price
     * Credentials are now passed from the WordPress plugin
     */
    public function calculatePrice(array $shipmentData, array $credentials = []): array
    {
        $payload = [
            'ACSAlias' => 'ACS_Price_Calculation',
            'ACSInputParameters' => array_merge([
                'Company_ID' => $credentials['company_id'] ?? '',
                'Company_Password' => $credentials['company_password'] ?? '',
                'User_ID' => $credentials['user_id'] ?? '',
                'User_Password' => $credentials['user_password'] ?? '',
                'Language' => 'GR',
            ], $shipmentData)
        ];

        return $this->makeApiCall($payload);
    }

    /**
     * Validate an address
     */
    public function validateAddress(string $address): array
    {
        $payload = [
            'ACSAlias' => 'ACS_Address_Validation',
            'ACSInputParameters' => [
                'Company_ID' => $this->companyId,
                'Company_Password' => $this->companyPassword,
                'User_ID' => $this->userId,
                'User_Password' => $this->userPassword,
                'Language' => 'GR',
                'Address' => $address
            ]
        ];

        return $this->makeApiCall($payload);
    }

    /**
     * Find ACS stations by zip code
     */
    public function findStationsByZipCode(string $zipCode): array
    {
        $payload = [
            'ACSAlias' => 'ACS_Area_Find_By_Zip_Code',
            'ACSInputParameters' => [
                'Company_ID' => $this->companyId,
                'Company_Password' => $this->companyPassword,
                'User_ID' => $this->userId,
                'User_Password' => $this->userPassword,
                'Language' => 'GR',
                'Zip_Code' => $zipCode,
                'Show_Only_Inaccessible_Areas' => 0,
                'Country' => 'GR'
            ]
        ];

        return $this->makeApiCall($payload);
    }

    /**
     * Get ACS stations
     */
    public function getStations(): array
    {
        $payload = [
            'ACSAlias' => 'Acs_Stations',
            'ACSInputParameters' => [
                'Company_ID' => $this->companyId,
                'Company_Password' => $this->companyPassword,
                'User_ID' => $this->userId,
                'User_Password' => $this->userPassword,
                'Language' => 'GR',
            ]
        ];

        return $this->makeApiCall($payload);
    }

    /**
     * Make HTTP API call to ACS
     */
    private function makeApiCall(array $payload): array
    {
        try {
            Log::info('ACS API Request', [
                'endpoint' => $this->apiEndpoint,
                'alias' => $payload['ACSAlias'] ?? 'unknown'
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'ACSApiKey' => $this->apiKey
            ])->timeout(30)->post($this->apiEndpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                // Check for API execution errors
                if (isset($data['ACSExecution_HasError']) && $data['ACSExecution_HasError'] === true) {
                    Log::error('ACS API Error', [
                        'message' => $data['ACSExecutionErrorMessage'] ?? 'Unknown error',
                        'payload' => $payload
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => $data['ACSExecutionErrorMessage'] ?? 'API Error',
                        'data' => null
                    ];
                }

                Log::info('ACS API Success', [
                    'alias' => $payload['ACSAlias'] ?? 'unknown',
                    'has_data' => isset($data['ACSOutputResponse']['ACSValueOutput'])
                ]);

                return [
                    'success' => true,
                    'error' => null,
                    'data' => $data
                ];

            } else {
                Log::error('ACS API HTTP Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'payload' => $payload
                ]);

                return [
                    'success' => false,
                    'error' => "HTTP {$response->status()}: {$response->body()}",
                    'data' => null
                ];
            }

        } catch (\Exception $e) {
            Log::error('ACS API Exception', [
                'message' => $e->getMessage(),
                'payload' => $payload
            ]);

            // Throw a more specific exception for better error handling
            throw new CourierApiException(
                'ACS API request failed: ' . $e->getMessage(),
                'ACS',
                [
                    'endpoint' => $this->apiEndpoint,
                    'alias' => $payload['ACSAlias'] ?? 'unknown',
                    'original_error' => $e->getMessage()
                ]
            );
        }
    }

    /**
     * Map ACS status to internal status
     */
    public static function mapStatusToInternal(string $acsStatus): string
    {
        $statusMap = [
            'Departure to destination' => 'in_transit',
            'Arrival-departure from HUB' => 'in_transit', 
            'Arrival to' => 'in_transit',
            'On delivery' => 'out_for_delivery',
            'Delivery to consignee' => 'delivered',
            'Delivery attempt failed' => 'failed',
            'Returned' => 'returned',
            'Picked up' => 'picked_up',
            'Scan' => 'picked_up',
        ];

        // Try to match partial strings
        foreach ($statusMap as $acsPattern => $internalStatus) {
            if (stripos($acsStatus, $acsPattern) !== false) {
                return $internalStatus;
            }
        }

        // Default to in_transit for unknown statuses
        return 'in_transit';
    }

    /**
     * Parse tracking events from ACS response
     */
    public static function parseTrackingEvents(array $acsResponse): array
    {
        if (!isset($acsResponse['ACSOutputResponse']['ACSValueOutput']['ACSTableOutput']['Table_Data'])) {
            return [];
        }

        $rawEvents = $acsResponse['ACSOutputResponse']['ACSValueOutput']['ACSTableOutput']['Table_Data'];
        
        if (!is_array($rawEvents)) {
            return [];
        }

        $events = [];
        foreach ($rawEvents as $event) {
            if (!isset($event['checkpoint_date_time'])) {
                continue;
            }

            $events[] = [
                'datetime' => $event['checkpoint_date_time'],
                'action' => $event['checkpoint_action'] ?? '',
                'location' => $event['checkpoint_location'] ?? '',
                'notes' => $event['checkpoint_notes'] ?? '',
                'status' => self::mapStatusToInternal($event['checkpoint_action'] ?? ''),
                'raw_data' => $event
            ];
        }

        // Sort by datetime descending (newest first)
        usort($events, function($a, $b) {
            return strtotime($b['datetime']) - strtotime($a['datetime']);
        });

        return $events;
    }
} 