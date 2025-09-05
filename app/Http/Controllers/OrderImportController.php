<?php

namespace App\Http\Controllers;

use App\Models\ImportLog;
use App\Models\Order;
use App\Jobs\ProcessOrderImport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderImportController extends Controller
{
    /**
     * Display the import interface
     */
    public function index(): Response
    {
        $tenant = Auth::user()->currentTenant();
        
        // Get recent imports
        $recentImports = ImportLog::forTenant($tenant->id)
            ->recent()
            ->limit(10)
            ->get()
            ->map(function ($import) {
                return [
                    'id' => $import->id,
                    'file_name' => $import->file_name,
                    'import_type' => $import->import_type,
                    'status' => $import->status,
                    'progress' => $import->getProgressPercentage(),
                    'success_rate' => $import->getSuccessRate(),
                    'total_rows' => $import->total_rows,
                    'orders_created' => $import->orders_created,
                    'orders_updated' => $import->orders_updated,
                    'has_errors' => $import->hasErrors(),
                    'created_at' => $import->created_at->format('M d, Y H:i'),
                    'processing_time' => $import->getProcessingTimeFormatted(),
                    'status_icon' => $import->getStatusIcon(),
                    'status_color' => $import->getStatusColor(),
                    'type_icon' => $import->getTypeIcon(),
                ];
            });

        // Get import statistics
        $stats = [
            'total_imports' => ImportLog::forTenant($tenant->id)->count(),
            'successful_imports' => ImportLog::forTenant($tenant->id)->completed()->count(),
            'failed_imports' => ImportLog::forTenant($tenant->id)->failed()->count(),
            'in_progress_imports' => ImportLog::forTenant($tenant->id)->inProgress()->count(),
            'total_orders_imported' => ImportLog::forTenant($tenant->id)->sum('orders_created'),
        ];

        return Inertia::render('Orders/Import/Index', [
            'recentImports' => $recentImports,
            'stats' => $stats,
            'supportedFormats' => [
                'CSV' => ['csv'],
                'Excel' => ['xlsx', 'xls'],
                'XML' => ['xml'],
                'JSON' => ['json'],
            ],
            'maxFileSize' => '10MB',
        ]);
    }

    /**
     * Handle file upload for import
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx,xls,xml,json',
                'max:10240', // 10MB
            ],
            'import_options' => 'array',
            'field_mapping' => 'array',
            'create_missing_customers' => 'boolean',
            'update_existing_orders' => 'boolean',
            'send_notifications' => 'boolean',
            'auto_create_shipments' => 'boolean',
            'default_status' => [
                'string',
                Rule::in(['pending', 'processing', 'ready_to_ship']),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenant = Auth::user()->currentTenant();
            $file = $request->file('file');
            
            // Generate unique filename
            $fileName = $file->getClientOriginalName();
            $fileHash = md5_file($file->getPathname());
            $storagePath = 'imports/' . $tenant->id . '/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            // Check for duplicate imports
            $existingImport = ImportLog::where('file_hash', $fileHash)
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', ['completed', 'processing'])
                ->first();
                
            if ($existingImport) {
                return response()->json([
                    'success' => false,
                    'message' => 'This file has already been imported successfully.',
                    'duplicate_import_id' => $existingImport->id,
                ], 409);
            }

            // Store the file
            $file->storeAs('', $storagePath);

            // Detect import type
            $importType = $this->detectImportType($file);

            // Create import log
            $importLog = ImportLog::create([
                'tenant_id' => $tenant->id,
                'user_id' => Auth::id(),
                'import_type' => $importType,
                'import_method' => 'file_upload',
                'source_name' => $fileName,
                'file_name' => $fileName,
                'file_path' => $storagePath,
                'file_size' => $file->getSize(),
                'file_hash' => $fileHash,
                'mime_type' => $file->getMimeType(),
                'status' => 'pending',
                
                // Import configuration
                'create_missing_customers' => $request->boolean('create_missing_customers', true),
                'update_existing_orders' => $request->boolean('update_existing_orders', false),
                'send_notifications' => $request->boolean('send_notifications', false),
                'auto_create_shipments' => $request->boolean('auto_create_shipments', false),
                'default_status' => $request->input('default_status', 'pending'),
                
                // Field mapping and options
                'field_mapping' => $request->input('field_mapping', []),
                'import_options' => $request->input('import_options', []),
                'notes' => $request->input('notes'),
            ]);

            // Dispatch job for processing
            ProcessOrderImport::dispatch($importLog);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully and queued for processing',
                'import_id' => $importLog->id,
                'status' => 'pending',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle API-based import
     */
    public function importFromApi(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'orders' => 'required|array|min:1',
            'orders.*.external_order_id' => 'required|string',
            'orders.*.total_amount' => 'required|numeric|min:0',
            'orders.*.shipping_address' => 'required|string',
            'orders.*.shipping_city' => 'required|string',
            'orders.*.shipping_postal_code' => 'required|string',
            'create_missing_customers' => 'boolean',
            'update_existing_orders' => 'boolean',
            'send_notifications' => 'boolean',
            'auto_create_shipments' => 'boolean',
            'default_status' => [
                'string',
                Rule::in(['pending', 'processing', 'ready_to_ship']),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenant = Auth::user()->currentTenant();
            $orders = $request->input('orders');

            // Create import log
            $importLog = ImportLog::create([
                'tenant_id' => $tenant->id,
                'user_id' => Auth::id(),
                'import_type' => 'api',
                'import_method' => 'api_call',
                'source_name' => 'API Import',
                'status' => 'pending',
                'total_rows' => count($orders),
                
                // Store orders data in metadata
                'metadata' => ['orders' => $orders],
                
                // Import configuration
                'create_missing_customers' => $request->boolean('create_missing_customers', true),
                'update_existing_orders' => $request->boolean('update_existing_orders', false),
                'send_notifications' => $request->boolean('send_notifications', false),
                'auto_create_shipments' => $request->boolean('auto_create_shipments', false),
                'default_status' => $request->input('default_status', 'pending'),
                
                // API specific data
                'api_endpoint' => $request->url(),
                'api_headers' => $request->headers->all(),
                'api_payload' => $request->getContent(),
                'notes' => $request->input('notes'),
            ]);

            // Dispatch job for processing
            ProcessOrderImport::dispatch($importLog);

            return response()->json([
                'success' => true,
                'message' => 'Orders queued for import processing',
                'import_id' => $importLog->id,
                'orders_count' => count($orders),
                'status' => 'pending',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process API import: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get import status and progress
     */
    public function getStatus(string $importId): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $importLog = ImportLog::where('id', $importId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$importLog) {
            return response()->json([
                'success' => false,
                'message' => 'Import not found',
            ], 404);
        }

        $summary = $importLog->getSummary();
        
        return response()->json([
            'success' => true,
            'import' => [
                'id' => $importLog->id,
                'status' => $importLog->status,
                'file_name' => $importLog->file_name,
                'import_type' => $importLog->import_type,
                'created_at' => $importLog->created_at->format('M d, Y H:i'),
                'started_at' => $importLog->started_at?->format('M d, Y H:i'),
                'completed_at' => $importLog->completed_at?->format('M d, Y H:i'),
                'summary' => $summary,
                'errors' => $importLog->errors,
                'warnings' => $importLog->warnings,
                'status_icon' => $importLog->getStatusIcon(),
                'status_color' => $importLog->getStatusColor(),
                'type_icon' => $importLog->getTypeIcon(),
            ],
        ]);
    }

    /**
     * Get detailed import results
     */
    public function getDetails(string $importId): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $importLog = ImportLog::where('id', $importId)
            ->where('tenant_id', $tenant->id)
            ->with(['orders' => function ($query) {
                $query->with('customer', 'items')
                      ->latest()
                      ->limit(50);
            }])
            ->first();

        if (!$importLog) {
            return response()->json([
                'success' => false,
                'message' => 'Import not found',
            ], 404);
        }

        $orders = $importLog->orders->map(function ($order) {
            return [
                'id' => $order->id,
                'external_order_id' => $order->external_order_id,
                'order_number' => $order->order_number,
                'customer_name' => $order->getCustomerDisplayName(),
                'total_amount' => $order->getFormattedTotal(),
                'status' => $order->status,
                'status_icon' => $order->getStatusIcon(),
                'items_count' => $order->items->count(),
                'created_at' => $order->created_at->format('M d, Y H:i'),
            ];
        });

        return response()->json([
            'success' => true,
            'import' => [
                'id' => $importLog->id,
                'status' => $importLog->status,
                'file_name' => $importLog->file_name,
                'summary' => $importLog->getSummary(),
                'errors' => $importLog->errors,
                'warnings' => $importLog->warnings,
                'field_mapping' => $importLog->field_mapping,
            ],
            'orders' => $orders,
        ]);
    }

    /**
     * Cancel an import
     */
    public function cancel(string $importId): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $importLog = ImportLog::where('id', $importId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$importLog) {
            return response()->json([
                'success' => false,
                'message' => 'Import not found',
            ], 404);
        }

        if ($importLog->isFinished()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel finished import',
            ], 400);
        }

        $importLog->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Import cancelled successfully',
        ]);
    }

    /**
     * Retry a failed import
     */
    public function retry(string $importId): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $importLog = ImportLog::where('id', $importId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$importLog) {
            return response()->json([
                'success' => false,
                'message' => 'Import not found',
            ], 404);
        }

        if (!$importLog->isFailed()) {
            return response()->json([
                'success' => false,
                'message' => 'Can only retry failed imports',
            ], 400);
        }

        // Reset import status
        $importLog->update([
            'status' => 'pending',
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'skipped_rows' => 0,
            'errors' => [],
            'warnings' => [],
            'error_log' => null,
            'started_at' => null,
            'completed_at' => null,
            'processing_time_seconds' => null,
        ]);

        // Dispatch job again
        ProcessOrderImport::dispatch($importLog);

        return response()->json([
            'success' => true,
            'message' => 'Import queued for retry',
        ]);
    }

    /**
     * Delete an import and its data
     */
    public function delete(string $importId): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $importLog = ImportLog::where('id', $importId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$importLog) {
            return response()->json([
                'success' => false,
                'message' => 'Import not found',
            ], 404);
        }

        // Delete associated file
        if ($importLog->hasFile()) {
            Storage::delete($importLog->file_path);
        }

        // Soft delete associated orders (they can be recovered if needed)
        Order::where('import_log_id', $importLog->id)->delete();

        // Delete import log
        $importLog->delete();

        return response()->json([
            'success' => true,
            'message' => 'Import deleted successfully',
        ]);
    }

    /**
     * Download sample import template
     */
    public function downloadTemplate(string $format): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $templates = [
            'csv' => [
                'filename' => 'order_import_template.csv',
                'content' => $this->generateCsvTemplate(),
                'mime' => 'text/csv',
            ],
            'xlsx' => [
                'filename' => 'order_import_template.xlsx',
                'content' => $this->generateExcelTemplate(),
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'json' => [
                'filename' => 'order_import_template.json',
                'content' => $this->generateJsonTemplate(),
                'mime' => 'application/json',
            ],
            'xml' => [
                'filename' => 'order_import_template.xml',
                'content' => $this->generateXmlTemplate(),
                'mime' => 'text/xml',
            ],
        ];

        if (!isset($templates[$format])) {
            abort(404, 'Template format not found');
        }

        $template = $templates[$format];
        $tempPath = storage_path('app/temp/' . $template['filename']);
        
        // Create temp directory if it doesn't exist
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        file_put_contents($tempPath, $template['content']);

        return response()->download($tempPath, $template['filename'], [
            'Content-Type' => $template['mime'],
        ])->deleteFileAfterSend(true);
    }

    /**
     * Get field mapping suggestions for CSV
     */
    public function getFieldMapping(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'headers' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $headers = $request->input('headers');
        $suggestions = [];

        foreach ($headers as $index => $header) {
            $normalized = strtolower(str_replace([' ', '_', '-'], '', $header));
            
            $suggestion = match($normalized) {
                'orderid', 'id', 'externalorderid' => 'external_order_id',
                'ordernumber', 'number' => 'order_number',
                'customername', 'name', 'customer' => 'customer_name',
                'customeremail', 'email' => 'customer_email',
                'customerphone', 'phone' => 'customer_phone',
                'address', 'shippingaddress' => 'shipping_address',
                'city', 'shippingcity' => 'shipping_city',
                'postalcode', 'zipcode', 'zip' => 'shipping_postal_code',
                'country', 'shippingcountry' => 'shipping_country',
                'total', 'totalamount', 'amount' => 'total_amount',
                'subtotal' => 'subtotal',
                'tax', 'taxamount' => 'tax_amount',
                'shipping', 'shippingcost' => 'shipping_cost',
                'discount', 'discountamount' => 'discount_amount',
                'currency' => 'currency',
                'status', 'orderstatus' => 'status',
                'paymentstatus' => 'payment_status',
                'paymentmethod' => 'payment_method',
                'orderdate', 'date' => 'order_date',
                default => null,
            };

            if ($suggestion) {
                $suggestions[$index] = $suggestion;
            }
        }

        // Get available fields for mapping
        $availableFields = [
            'external_order_id' => 'Order ID (Required)',
            'order_number' => 'Order Number',
            'customer_name' => 'Customer Name',
            'customer_email' => 'Customer Email',
            'customer_phone' => 'Customer Phone',
            'shipping_address' => 'Shipping Address (Required)',
            'shipping_city' => 'Shipping City (Required)',
            'shipping_postal_code' => 'Postal Code (Required)',
            'shipping_country' => 'Country',
            'billing_address' => 'Billing Address',
            'billing_city' => 'Billing City',
            'billing_postal_code' => 'Billing Postal Code',
            'billing_country' => 'Billing Country',
            'total_amount' => 'Total Amount (Required)',
            'subtotal' => 'Subtotal',
            'tax_amount' => 'Tax Amount',
            'shipping_cost' => 'Shipping Cost',
            'discount_amount' => 'Discount Amount',
            'currency' => 'Currency',
            'status' => 'Order Status',
            'payment_status' => 'Payment Status',
            'payment_method' => 'Payment Method',
            'order_date' => 'Order Date',
            'special_instructions' => 'Special Instructions',
            'shipping_notes' => 'Shipping Notes',
        ];

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
            'available_fields' => $availableFields,
        ]);
    }

    /**
     * Detect import type from file
     */
    protected function detectImportType($file): string
    {
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();

        return match(true) {
            in_array($extension, ['csv', 'txt']) => 'csv',
            in_array($extension, ['xlsx', 'xls']) => 'csv', // Will be converted to CSV
            in_array($extension, ['xml']) => 'xml',
            in_array($extension, ['json']) => 'json',
            str_contains($mimeType, 'csv') => 'csv',
            str_contains($mimeType, 'xml') => 'xml',
            str_contains($mimeType, 'json') => 'json',
            default => 'csv',
        };
    }

    /**
     * Generate CSV template
     */
    protected function generateCsvTemplate(): string
    {
        $headers = [
            'external_order_id',
            'order_number',
            'customer_name',
            'customer_email',
            'customer_phone',
            'shipping_address',
            'shipping_city',
            'shipping_postal_code',
            'shipping_country',
            'total_amount',
            'currency',
            'status',
            'payment_status',
            'order_date',
        ];

        $sampleData = [
            'ORD-001',
            '2025001',
            'Γιάννης Παπαδόπουλος',
            'giannis@example.com',
            '6912345678',
            'Πατησίων 123',
            'Αθήνα',
            '10434',
            'GR',
            '89.50',
            'EUR',
            'pending',
            'paid',
            '2025-01-22 10:30:00',
        ];

        $csv = implode(',', $headers) . "\n";
        $csv .= implode(',', array_map(function($field) {
            return '"' . str_replace('"', '""', $field) . '"';
        }, $sampleData)) . "\n";

        return $csv;
    }

    /**
     * Generate Excel template (as CSV for now)
     */
    protected function generateExcelTemplate(): string
    {
        return $this->generateCsvTemplate();
    }

    /**
     * Generate JSON template
     */
    protected function generateJsonTemplate(): string
    {
        $template = [
            'orders' => [
                [
                    'external_order_id' => 'ORD-001',
                    'order_number' => '2025001',
                    'customer_name' => 'Γιάννης Παπαδόπουλος',
                    'customer_email' => 'giannis@example.com',
                    'customer_phone' => '6912345678',
                    'shipping_address' => 'Πατησίων 123',
                    'shipping_city' => 'Αθήνα',
                    'shipping_postal_code' => '10434',
                    'shipping_country' => 'GR',
                    'total_amount' => 89.50,
                    'subtotal' => 72.50,
                    'tax_amount' => 17.00,
                    'shipping_cost' => 0.00,
                    'discount_amount' => 0.00,
                    'currency' => 'EUR',
                    'status' => 'pending',
                    'payment_status' => 'paid',
                    'payment_method' => 'credit_card',
                    'order_date' => '2025-01-22T10:30:00Z',
                    'items' => [
                        [
                            'product_name' => 'Smartphone Samsung Galaxy S24',
                            'product_sku' => 'SAMSUNG-S24-256',
                            'quantity' => 1,
                            'unit_price' => 72.50,
                            'final_unit_price' => 72.50,
                            'total_price' => 72.50,
                        ]
                    ]
                ]
            ]
        ];

        return json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate XML template
     */
    protected function generateXmlTemplate(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<orders>
    <order>
        <external_order_id>ORD-001</external_order_id>
        <order_number>2025001</order_number>
        <customer_name>Γιάννης Παπαδόπουλος</customer_name>
        <customer_email>giannis@example.com</customer_email>
        <customer_phone>6912345678</customer_phone>
        <shipping_address>Πατησίων 123</shipping_address>
        <shipping_city>Αθήνα</shipping_city>
        <shipping_postal_code>10434</shipping_postal_code>
        <shipping_country>GR</shipping_country>
        <total_amount>89.50</total_amount>
        <subtotal>72.50</subtotal>
        <tax_amount>17.00</tax_amount>
        <shipping_cost>0.00</shipping_cost>
        <discount_amount>0.00</discount_amount>
        <currency>EUR</currency>
        <status>pending</status>
        <payment_status>paid</payment_status>
        <payment_method>credit_card</payment_method>
        <order_date>2025-01-22T10:30:00Z</order_date>
        <items>
            <item>
                <product_name>Smartphone Samsung Galaxy S24</product_name>
                <product_sku>SAMSUNG-S24-256</product_sku>
                <quantity>1</quantity>
                <unit_price>72.50</unit_price>
                <final_unit_price>72.50</final_unit_price>
                <total_price>72.50</total_price>
            </item>
        </items>
    </order>
</orders>';
    }
}
