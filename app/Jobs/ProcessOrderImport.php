<?php

namespace App\Jobs;

use App\Models\ImportLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProcessOrderImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ImportLog $importLog;
    protected array $config;

    public $tries = 3;
    public $timeout = 3600; // 1 hour

    /**
     * Create a new job instance.
     */
    public function __construct(ImportLog $importLog)
    {
        $this->importLog = $importLog;
        $this->config = [
            'create_missing_customers' => $importLog->create_missing_customers,
            'update_existing_orders' => $importLog->update_existing_orders,
            'send_notifications' => $importLog->send_notifications,
            'auto_create_shipments' => $importLog->auto_create_shipments,
            'default_status' => $importLog->default_status,
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->importLog->start();
            
            Log::info('Starting order import process', [
                'import_log_id' => $this->importLog->id,
                'tenant_id' => $this->importLog->tenant_id,
                'import_type' => $this->importLog->import_type,
            ]);

            // Process based on import type
            switch ($this->importLog->import_type) {
                case 'csv':
                    $this->processCsvImport();
                    break;
                case 'xml':
                    $this->processXmlImport();
                    break;
                case 'json':
                    $this->processJsonImport();
                    break;
                case 'api':
                    $this->processApiImport();
                    break;
                default:
                    throw new \Exception('Unsupported import type: ' . $this->importLog->import_type);
            }

            $this->importLog->complete();
            
            Log::info('Order import completed', [
                'import_log_id' => $this->importLog->id,
                'summary' => $this->importLog->getSummary(),
            ]);

        } catch (\Exception $e) {
            $this->importLog->fail($e->getMessage());
            
            Log::error('Order import failed', [
                'import_log_id' => $this->importLog->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Process CSV import
     */
    protected function processCsvImport(): void
    {
        if (!$this->importLog->hasFile()) {
            throw new \Exception('CSV file not found');
        }

        $filePath = storage_path('app/' . $this->importLog->file_path);
        $file = fopen($filePath, 'r');
        
        if (!$file) {
            throw new \Exception('Could not open CSV file');
        }

        // Read header row
        $headers = fgetcsv($file);
        if (!$headers) {
            throw new \Exception('CSV file appears to be empty or invalid');
        }

        // Normalize headers for mapping
        $headers = array_map('trim', $headers);
        $mapping = $this->getFieldMapping($headers);

        $totalRows = 0;
        $processedRows = 0;
        $successfulRows = 0;
        $failedRows = 0;
        $skippedRows = 0;

        // Count total rows first
        while (fgetcsv($file) !== false) {
            $totalRows++;
        }
        
        $this->importLog->update(['total_rows' => $totalRows]);
        
        // Reset file pointer
        rewind($file);
        fgetcsv($file); // Skip header row

        // Process each row
        while (($row = fgetcsv($file)) !== false) {
            $processedRows++;
            
            try {
                $mappedData = $this->mapRowData($row, $headers, $mapping);
                
                if ($this->shouldSkipRow($mappedData)) {
                    $skippedRows++;
                    continue;
                }

                DB::transaction(function () use ($mappedData) {
                    $this->processOrderRow($mappedData);
                });

                $successfulRows++;
                
            } catch (\Exception $e) {
                $failedRows++;
                $this->importLog->addError($e->getMessage(), $processedRows);
                
                Log::warning('Failed to process CSV row', [
                    'import_log_id' => $this->importLog->id,
                    'row' => $processedRows,
                    'error' => $e->getMessage(),
                ]);
            }

            // Update progress every 100 rows
            if ($processedRows % 100 === 0) {
                $this->importLog->updateProgress($processedRows, $successfulRows, $failedRows, $skippedRows);
            }
        }

        fclose($file);
        $this->importLog->updateProgress($processedRows, $successfulRows, $failedRows, $skippedRows);
    }

    /**
     * Process XML import
     */
    protected function processXmlImport(): void
    {
        if (!$this->importLog->hasFile()) {
            throw new \Exception('XML file not found');
        }

        $filePath = storage_path('app/' . $this->importLog->file_path);
        $xmlContent = file_get_contents($filePath);
        
        if (!$xmlContent) {
            throw new \Exception('Could not read XML file');
        }

        $xml = simplexml_load_string($xmlContent);
        
        if (!$xml) {
            throw new \Exception('Invalid XML format');
        }

        // Convert XML to array for processing
        $orders = $this->xmlToArray($xml);
        
        $totalRows = count($orders);
        $this->importLog->update(['total_rows' => $totalRows]);

        $processedRows = 0;
        $successfulRows = 0;
        $failedRows = 0;
        $skippedRows = 0;

        foreach ($orders as $orderData) {
            $processedRows++;
            
            try {
                if ($this->shouldSkipRow($orderData)) {
                    $skippedRows++;
                    continue;
                }

                DB::transaction(function () use ($orderData) {
                    $this->processOrderRow($orderData);
                });

                $successfulRows++;
                
            } catch (\Exception $e) {
                $failedRows++;
                $this->importLog->addError($e->getMessage(), $processedRows);
            }

            // Update progress every 50 rows
            if ($processedRows % 50 === 0) {
                $this->importLog->updateProgress($processedRows, $successfulRows, $failedRows, $skippedRows);
            }
        }

        $this->importLog->updateProgress($processedRows, $successfulRows, $failedRows, $skippedRows);
    }

    /**
     * Process JSON import
     */
    protected function processJsonImport(): void
    {
        if (!$this->importLog->hasFile()) {
            throw new \Exception('JSON file not found');
        }

        $filePath = storage_path('app/' . $this->importLog->file_path);
        $jsonContent = file_get_contents($filePath);
        
        if (!$jsonContent) {
            throw new \Exception('Could not read JSON file');
        }

        $data = json_decode($jsonContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON format: ' . json_last_error_msg());
        }

        // Normalize data structure
        $orders = $data['orders'] ?? $data['data'] ?? $data;
        
        if (!is_array($orders)) {
            throw new \Exception('JSON must contain an array of orders');
        }

        $totalRows = count($orders);
        $this->importLog->update(['total_rows' => $totalRows]);

        $processedRows = 0;
        $successfulRows = 0;
        $failedRows = 0;
        $skippedRows = 0;

        foreach ($orders as $orderData) {
            $processedRows++;
            
            try {
                if ($this->shouldSkipRow($orderData)) {
                    $skippedRows++;
                    continue;
                }

                DB::transaction(function () use ($orderData) {
                    $this->processOrderRow($orderData);
                });

                $successfulRows++;
                
            } catch (\Exception $e) {
                $failedRows++;
                $this->importLog->addError($e->getMessage(), $processedRows);
            }

            // Update progress every 50 rows
            if ($processedRows % 50 === 0) {
                $this->importLog->updateProgress($processedRows, $successfulRows, $failedRows, $skippedRows);
            }
        }

        $this->importLog->updateProgress($processedRows, $successfulRows, $failedRows, $skippedRows);
    }

    /**
     * Process API import
     */
    protected function processApiImport(): void
    {
        // For API imports, data should already be normalized
        $orders = $this->importLog->metadata['orders'] ?? [];
        
        if (empty($orders)) {
            throw new \Exception('No orders found in API data');
        }

        $totalRows = count($orders);
        $this->importLog->update(['total_rows' => $totalRows]);

        $processedRows = 0;
        $successfulRows = 0;
        $failedRows = 0;

        foreach ($orders as $orderData) {
            $processedRows++;
            
            try {
                DB::transaction(function () use ($orderData) {
                    $this->processOrderRow($orderData);
                });

                $successfulRows++;
                
            } catch (\Exception $e) {
                $failedRows++;
                $this->importLog->addError($e->getMessage(), $processedRows);
            }
        }

        $this->importLog->updateProgress($processedRows, $successfulRows, $failedRows);
    }

    /**
     * Process a single order row
     */
    protected function processOrderRow(array $data): void
    {
        // Validate required fields
        $this->validateOrderData($data);

        // Handle customer
        $customer = $this->handleCustomer($data);

        // Check for existing order
        $existingOrder = Order::where('external_order_id', $data['external_order_id'])
                             ->where('tenant_id', $this->importLog->tenant_id)
                             ->first();

        if ($existingOrder && !$this->config['update_existing_orders']) {
            $this->importLog->addWarning('Order already exists, skipping: ' . $data['external_order_id']);
            return;
        }

        // Create or update order
        if ($existingOrder) {
            $order = $this->updateOrder($existingOrder, $data, $customer);
            $this->importLog->incrementCounts(orders_updated: 1);
        } else {
            $order = $this->createOrder($data, $customer);
            $this->importLog->incrementCounts(orders_created: 1);
        }

        // Handle order items
        if (isset($data['items']) && is_array($data['items'])) {
            $this->handleOrderItems($order, $data['items']);
        }

        // Auto-create shipment if configured
        if ($this->config['auto_create_shipments'] && $order->isReadyToShip()) {
            $order->createShipment();
        }
    }

    /**
     * Handle customer creation/update
     */
    protected function handleCustomer(array $data): ?Customer
    {
        if (empty($data['customer_email'])) {
            return null;
        }

        $customer = Customer::where('email', $data['customer_email'])
                           ->where('tenant_id', $this->importLog->tenant_id)
                           ->first();

        if ($customer) {
            // Update customer info if needed
            $updates = [];
            if (!empty($data['customer_name']) && $data['customer_name'] !== $customer->full_name) {
                $nameParts = explode(' ', $data['customer_name'], 2);
                $updates['first_name'] = $nameParts[0];
                $updates['last_name'] = $nameParts[1] ?? '';
                $updates['full_name'] = $data['customer_name'];
            }
            
            if (!empty($data['customer_phone']) && $data['customer_phone'] !== $customer->phone) {
                $updates['phone'] = $data['customer_phone'];
            }

            if (!empty($updates)) {
                $customer->update($updates);
                $this->importLog->incrementCounts(customers_updated: 1);
            }

            return $customer;
        }

        if (!$this->config['create_missing_customers']) {
            return null;
        }

        // Create new customer
        $nameParts = explode(' ', $data['customer_name'] ?? '', 2);
        
        $customer = Customer::create([
            'tenant_id' => $this->importLog->tenant_id,
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? '',
            'full_name' => $data['customer_name'] ?? '',
            'email' => $data['customer_email'],
            'phone' => $data['customer_phone'] ?? null,
            'source' => 'import',
        ]);

        $this->importLog->incrementCounts(customers_created: 1);
        
        return $customer;
    }

    /**
     * Create new order
     */
    protected function createOrder(array $data, ?Customer $customer): Order
    {
        return Order::create([
            'tenant_id' => $this->importLog->tenant_id,
            'customer_id' => $customer?->id,
            'import_log_id' => $this->importLog->id,
            'external_order_id' => $data['external_order_id'],
            'order_number' => $data['order_number'] ?? null,
            'import_source' => $this->importLog->import_type,
            'status' => $data['status'] ?? $this->config['default_status'],
            
            // Customer information
            'customer_name' => $data['customer_name'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            
            // Addresses
            'shipping_address' => $data['shipping_address'],
            'shipping_city' => $data['shipping_city'],
            'shipping_postal_code' => $data['shipping_postal_code'],
            'shipping_country' => $data['shipping_country'] ?? 'GR',
            'shipping_notes' => $data['shipping_notes'] ?? null,
            
            'billing_address' => $data['billing_address'] ?? null,
            'billing_city' => $data['billing_city'] ?? null,
            'billing_postal_code' => $data['billing_postal_code'] ?? null,
            'billing_country' => $data['billing_country'] ?? null,
            
            // Totals
            'subtotal' => $data['subtotal'] ?? 0,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'shipping_cost' => $data['shipping_cost'] ?? 0,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'total_amount' => $data['total_amount'],
            'currency' => $data['currency'] ?? 'EUR',
            
            // Payment
            'payment_status' => $data['payment_status'] ?? 'pending',
            'payment_method' => $data['payment_method'] ?? null,
            'payment_reference' => $data['payment_reference'] ?? null,
            'payment_date' => $this->parseDate($data['payment_date'] ?? null),
            
            // Shipping preferences
            'preferred_courier' => $data['preferred_courier'] ?? null,
            'shipping_method' => $data['shipping_method'] ?? 'standard',
            'requires_signature' => $data['requires_signature'] ?? false,
            'fragile_items' => $data['fragile_items'] ?? false,
            'total_weight' => $data['total_weight'] ?? null,
            'package_dimensions' => $data['package_dimensions'] ?? null,
            
            // Instructions
            'special_instructions' => $data['special_instructions'] ?? null,
            'delivery_preferences' => $data['delivery_preferences'] ?? null,
            
            // Dates
            'order_date' => $this->parseDate($data['order_date']) ?: now(),
            'expected_ship_date' => $this->parseDate($data['expected_ship_date'] ?? null),
            
            // Metadata
            'additional_data' => $data['additional_data'] ?? null,
            'import_notes' => $data['import_notes'] ?? null,
        ]);
    }

    /**
     * Update existing order
     */
    protected function updateOrder(Order $order, array $data, ?Customer $customer): Order
    {
        $updateData = [
            'customer_id' => $customer?->id ?? $order->customer_id,
            'status' => $data['status'] ?? $order->status,
            
            // Only update certain fields
            'order_number' => $data['order_number'] ?? $order->order_number,
            'payment_status' => $data['payment_status'] ?? $order->payment_status,
            'payment_reference' => $data['payment_reference'] ?? $order->payment_reference,
            'payment_date' => $this->parseDate($data['payment_date'] ?? null) ?: $order->payment_date,
            'expected_ship_date' => $this->parseDate($data['expected_ship_date'] ?? null) ?: $order->expected_ship_date,
        ];

        $order->update($updateData);
        return $order;
    }

    /**
     * Handle order items
     */
    protected function handleOrderItems(Order $order, array $items): void
    {
        foreach ($items as $itemData) {
            OrderItem::create([
                'order_id' => $order->id,
                'tenant_id' => $order->tenant_id,
                
                'product_sku' => $itemData['product_sku'] ?? null,
                'product_name' => $itemData['product_name'],
                'product_description' => $itemData['product_description'] ?? null,
                'product_category' => $itemData['product_category'] ?? null,
                'product_brand' => $itemData['product_brand'] ?? null,
                'product_model' => $itemData['product_model'] ?? null,
                'product_attributes' => $itemData['product_attributes'] ?? null,
                
                'external_product_id' => $itemData['external_product_id'] ?? null,
                'external_variant_id' => $itemData['external_variant_id'] ?? null,
                'product_url' => $itemData['product_url'] ?? null,
                'product_images' => $itemData['product_images'] ?? null,
                
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'discount_amount' => $itemData['discount_amount'] ?? 0,
                'final_unit_price' => $itemData['final_unit_price'] ?? $itemData['unit_price'],
                'total_price' => $itemData['total_price'] ?? ($itemData['quantity'] * $itemData['unit_price']),
                
                'tax_rate' => $itemData['tax_rate'] ?? 0,
                'tax_amount' => $itemData['tax_amount'] ?? 0,
                'tax_class' => $itemData['tax_class'] ?? null,
                
                'weight' => $itemData['weight'] ?? null,
                'dimensions' => $itemData['dimensions'] ?? null,
                'is_digital' => $itemData['is_digital'] ?? false,
                'requires_special_handling' => $itemData['requires_special_handling'] ?? false,
                'is_fragile' => $itemData['is_fragile'] ?? false,
                'is_hazardous' => $itemData['is_hazardous'] ?? false,
                
                'fulfillment_status' => $itemData['fulfillment_status'] ?? 'pending',
                'custom_fields' => $itemData['custom_fields'] ?? null,
                'special_instructions' => $itemData['special_instructions'] ?? null,
            ]);
        }
    }

    /**
     * Get field mapping for CSV headers
     */
    protected function getFieldMapping(array $headers): array
    {
        $mapping = $this->importLog->field_mapping ?? [];
        
        if (empty($mapping)) {
            // Auto-detect mapping based on common field names
            $mapping = $this->autoDetectFieldMapping($headers);
        }
        
        return $mapping;
    }

    /**
     * Auto-detect field mapping
     */
    protected function autoDetectFieldMapping(array $headers): array
    {
        $mapping = [];
        
        foreach ($headers as $index => $header) {
            $normalized = strtolower(str_replace([' ', '_', '-'], '', $header));
            
            $mapping[$index] = match($normalized) {
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
        }
        
        return array_filter($mapping);
    }

    /**
     * Map CSV row data
     */
    protected function mapRowData(array $row, array $headers, array $mapping): array
    {
        $mapped = [];
        
        foreach ($mapping as $csvIndex => $fieldName) {
            if (isset($row[$csvIndex]) && $fieldName) {
                $mapped[$fieldName] = trim($row[$csvIndex]);
            }
        }
        
        return $mapped;
    }

    /**
     * Check if row should be skipped
     */
    protected function shouldSkipRow(array $data): bool
    {
        // Skip if missing required fields
        return empty($data['external_order_id']) || empty($data['total_amount']);
    }

    /**
     * Validate order data
     */
    protected function validateOrderData(array $data): void
    {
        if (empty($data['external_order_id'])) {
            throw new \InvalidArgumentException('External order ID is required');
        }
        
        if (empty($data['total_amount']) || !is_numeric($data['total_amount'])) {
            throw new \InvalidArgumentException('Valid total amount is required');
        }
        
        if (empty($data['shipping_address'])) {
            throw new \InvalidArgumentException('Shipping address is required');
        }
        
        if (empty($data['shipping_city'])) {
            throw new \InvalidArgumentException('Shipping city is required');
        }
        
        if (empty($data['shipping_postal_code'])) {
            throw new \InvalidArgumentException('Shipping postal code is required');
        }
    }

    /**
     * Parse date string
     */
    protected function parseDate(?string $dateString): ?Carbon
    {
        if (empty($dateString)) {
            return null;
        }
        
        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Convert XML to array
     */
    protected function xmlToArray(\SimpleXMLElement $xml): array
    {
        return json_decode(json_encode($xml), true);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        $this->importLog->fail($exception->getMessage());
        
        Log::error('Order import job failed permanently', [
            'import_log_id' => $this->importLog->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
