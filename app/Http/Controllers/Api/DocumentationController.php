<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;

class DocumentationController extends Controller
{
    /**
     * Generate API documentation
     */
    public function index(Request $request): JsonResponse
    {
        $version = $request->get('version', 'v1');
        
        return response()->json([
            'success' => true,
            'data' => [
                'api_info' => $this->getApiInfo($version),
                'endpoints' => $this->getEndpoints($version),
                'authentication' => $this->getAuthenticationInfo(),
                'rate_limits' => $this->getRateLimitInfo(),
                'error_codes' => $this->getErrorCodes(),
                'examples' => $this->getExamples(),
            ],
            'meta' => [
                'version' => $version,
                'generated_at' => now()->toISOString(),
                'documentation_version' => '1.0.0',
            ]
        ]);
    }

    /**
     * Get API information
     */
    private function getApiInfo(string $version): array
    {
        return [
            'name' => 'DM Delivery API',
            'version' => $version,
            'description' => 'Comprehensive delivery tracking and management API',
            'base_url' => config('app.url') . '/api/' . $version,
            'contact' => [
                'email' => 'api-support@dmmelivery.com',
                'website' => 'https://dmmelivery.com',
            ],
            'license' => [
                'name' => 'MIT',
                'url' => 'https://opensource.org/licenses/MIT',
            ],
        ];
    }

    /**
     * Get all API endpoints
     */
    private function getEndpoints(string $version): array
    {
        $endpoints = [];
        
        // Health check
        $endpoints['health'] = [
            'method' => 'GET',
            'path' => '/health',
            'description' => 'Check API health status',
            'authentication' => false,
            'parameters' => [],
            'responses' => [
                '200' => [
                    'description' => 'API is healthy',
                    'example' => [
                        'success' => true,
                        'data' => [
                            'status' => 'healthy',
                            'timestamp' => now()->toISOString(),
                        ]
                    ]
                ]
            ]
        ];

        // Shipments
        $endpoints['shipments'] = [
            'index' => [
                'method' => 'GET',
                'path' => '/shipments',
                'description' => 'List all shipments',
                'authentication' => true,
                'parameters' => [
                    'status' => ['type' => 'string', 'description' => 'Filter by status'],
                    'courier_id' => ['type' => 'integer', 'description' => 'Filter by courier ID'],
                    'customer_id' => ['type' => 'integer', 'description' => 'Filter by customer ID'],
                    'date_from' => ['type' => 'date', 'description' => 'Filter from date'],
                    'date_to' => ['type' => 'date', 'description' => 'Filter to date'],
                    'per_page' => ['type' => 'integer', 'description' => 'Items per page (max 100)'],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'List of shipments',
                        'example' => [
                            'success' => true,
                            'data' => [],
                            'pagination' => [
                                'current_page' => 1,
                                'per_page' => 15,
                                'total' => 100,
                                'last_page' => 7,
                                'has_more' => true,
                            ]
                        ]
                    ]
                ]
            ],
            'store' => [
                'method' => 'POST',
                'path' => '/shipments',
                'description' => 'Create a new shipment',
                'authentication' => true,
                'parameters' => [
                    'order_id' => ['type' => 'integer', 'required' => true, 'description' => 'Order ID'],
                    'courier_id' => ['type' => 'integer', 'required' => false, 'description' => 'Courier ID'],
                    'tracking_number' => ['type' => 'string', 'required' => false, 'description' => 'Tracking number'],
                    'weight' => ['type' => 'number', 'required' => false, 'description' => 'Package weight'],
                    'shipping_address' => ['type' => 'string', 'required' => true, 'description' => 'Shipping address'],
                    'billing_address' => ['type' => 'string', 'required' => false, 'description' => 'Billing address'],
                    'shipping_cost' => ['type' => 'number', 'required' => false, 'description' => 'Shipping cost'],
                    'estimated_delivery' => ['type' => 'datetime', 'required' => false, 'description' => 'Estimated delivery date'],
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Shipment created successfully',
                        'example' => [
                            'success' => true,
                            'message' => 'Shipment created successfully',
                            'data' => [
                                'id' => 1,
                                'tracking_number' => 'TRK123456',
                                'status' => 'pending',
                                'created_at' => now()->toISOString(),
                            ]
                        ]
                    ],
                    '422' => [
                        'description' => 'Validation failed',
                        'example' => [
                            'success' => false,
                            'message' => 'Validation failed',
                            'errors' => [
                                'order_id' => ['The order id field is required.']
                            ]
                        ]
                    ]
                ]
            ],
            'show' => [
                'method' => 'GET',
                'path' => '/shipments/{id}',
                'description' => 'Get shipment details',
                'authentication' => true,
                'parameters' => [
                    'id' => ['type' => 'integer', 'required' => true, 'description' => 'Shipment ID'],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Shipment details',
                        'example' => [
                            'success' => true,
                            'data' => [
                                'id' => 1,
                                'tracking_number' => 'TRK123456',
                                'status' => 'in_transit',
                                'customer' => ['name' => 'John Doe'],
                                'courier' => ['name' => 'ACS'],
                            ]
                        ]
                    ],
                    '404' => [
                        'description' => 'Shipment not found',
                        'example' => [
                            'success' => false,
                            'message' => 'Shipment not found',
                            'error_code' => 'SHIPMENT_NOT_FOUND'
                        ]
                    ]
                ]
            ],
            'update' => [
                'method' => 'PUT',
                'path' => '/shipments/{id}',
                'description' => 'Update shipment',
                'authentication' => true,
                'parameters' => [
                    'id' => ['type' => 'integer', 'required' => true, 'description' => 'Shipment ID'],
                    'status' => ['type' => 'string', 'required' => false, 'description' => 'Shipment status'],
                    'tracking_number' => ['type' => 'string', 'required' => false, 'description' => 'Tracking number'],
                    'weight' => ['type' => 'number', 'required' => false, 'description' => 'Package weight'],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Shipment updated successfully',
                        'example' => [
                            'success' => true,
                            'message' => 'Shipment updated successfully',
                            'data' => []
                        ]
                    ]
                ]
            ],
            'destroy' => [
                'method' => 'DELETE',
                'path' => '/shipments/{id}',
                'description' => 'Delete shipment',
                'authentication' => true,
                'parameters' => [
                    'id' => ['type' => 'integer', 'required' => true, 'description' => 'Shipment ID'],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Shipment deleted successfully',
                        'example' => [
                            'success' => true,
                            'message' => 'Shipment deleted successfully'
                        ]
                    ]
                ]
            ]
        ];

        // Analytics
        $endpoints['analytics'] = [
            'dashboard' => [
                'method' => 'GET',
                'path' => '/analytics/dashboard',
                'description' => 'Get comprehensive analytics dashboard',
                'authentication' => true,
                'parameters' => [
                    'start_date' => ['type' => 'date', 'required' => false, 'description' => 'Start date for analytics'],
                    'end_date' => ['type' => 'date', 'required' => false, 'description' => 'End date for analytics'],
                    'period' => ['type' => 'string', 'required' => false, 'description' => 'Period (daily, weekly, monthly)'],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Analytics data',
                        'example' => [
                            'success' => true,
                            'data' => [
                                'overview' => [
                                    'total_shipments' => 1000,
                                    'delivered_shipments' => 950,
                                    'success_rate' => 95.0,
                                ],
                                'performance' => [
                                    'performance_score' => 85.5,
                                    'on_time_rate' => 90.0,
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $endpoints;
    }

    /**
     * Get authentication information
     */
    private function getAuthenticationInfo(): array
    {
        return [
            'type' => 'Bearer Token',
            'description' => 'Use Laravel Sanctum for API authentication',
            'header' => 'Authorization: Bearer {token}',
            'token_obtainment' => [
                'endpoint' => '/api/auth/login',
                'method' => 'POST',
                'parameters' => [
                    'email' => 'string',
                    'password' => 'string',
                ],
                'response' => [
                    'token' => 'string',
                    'user' => 'object',
                ]
            ],
            'rate_limits' => [
                'authenticated' => '1000 requests per hour',
                'unauthenticated' => '100 requests per hour',
            ]
        ];
    }

    /**
     * Get rate limit information
     */
    private function getRateLimitInfo(): array
    {
        return [
            'limits' => [
                'default' => '1000 requests per hour',
                'analytics' => '100 requests per hour',
                'webhooks' => '300 requests per hour',
            ],
            'headers' => [
                'X-RateLimit-Limit' => 'Maximum requests allowed',
                'X-RateLimit-Remaining' => 'Remaining requests in current window',
                'X-RateLimit-Reset' => 'Time when the rate limit resets',
            ],
            'exceeded_response' => [
                'status' => 429,
                'body' => [
                    'success' => false,
                    'message' => 'Rate limit exceeded',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                ]
            ]
        ];
    }

    /**
     * Get error codes
     */
    private function getErrorCodes(): array
    {
        return [
            '400' => [
                'code' => 'BAD_REQUEST',
                'description' => 'Invalid request parameters',
            ],
            '401' => [
                'code' => 'UNAUTHENTICATED',
                'description' => 'Authentication required',
            ],
            '403' => [
                'code' => 'FORBIDDEN',
                'description' => 'Access denied',
            ],
            '404' => [
                'code' => 'NOT_FOUND',
                'description' => 'Resource not found',
            ],
            '422' => [
                'code' => 'VALIDATION_ERROR',
                'description' => 'Validation failed',
            ],
            '429' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'description' => 'Rate limit exceeded',
            ],
            '500' => [
                'code' => 'INTERNAL_ERROR',
                'description' => 'Internal server error',
            ],
            '502' => [
                'code' => 'COURIER_API_ERROR',
                'description' => 'Courier API error',
            ],
        ];
    }

    /**
     * Get API examples
     */
    private function getExamples(): array
    {
        return [
            'authentication' => [
                'curl' => 'curl -X POST "https://api.dmmelivery.com/api/auth/login" -H "Content-Type: application/json" -d \'{"email":"user@example.com","password":"password"}\'',
                'response' => [
                    'success' => true,
                    'data' => [
                        'token' => '1|abcdef123456...',
                        'user' => [
                            'id' => 1,
                            'name' => 'John Doe',
                            'email' => 'user@example.com',
                        ]
                    ]
                ]
            ],
            'create_shipment' => [
                'curl' => 'curl -X POST "https://api.dmmelivery.com/api/v1/shipments" -H "Authorization: Bearer {token}" -H "Content-Type: application/json" -d \'{"order_id":1,"tracking_number":"TRK123456","shipping_address":"123 Main St, City, Country"}\'',
                'response' => [
                    'success' => true,
                    'message' => 'Shipment created successfully',
                    'data' => [
                        'id' => 1,
                        'tracking_number' => 'TRK123456',
                        'status' => 'pending',
                        'created_at' => '2024-01-01T00:00:00Z',
                    ]
                ]
            ],
            'get_shipment_status' => [
                'curl' => 'curl -X GET "https://api.dmmelivery.com/api/v1/public/shipments/TRK123456/status"',
                'response' => [
                    'success' => true,
                    'data' => [
                        'tracking_number' => 'TRK123456',
                        'status' => 'in_transit',
                        'current_location' => 'Distribution Center',
                        'estimated_delivery' => '2024-01-03T00:00:00Z',
                        'courier' => 'ACS',
                        'last_updated' => '2024-01-02T10:30:00Z',
                    ]
                ]
            ]
        ];
    }
}
