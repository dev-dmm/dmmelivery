<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\Courier;

class WooCommerceOrderController extends Controller
{
    /**
     * Map WordPress/WooCommerce statuses to our internal statuses
     */
    private function mapOrderStatus(string $wooStatus): string
    {
        $statusMapping = [
            // WordPress/WooCommerce statuses -> Our statuses
            'pending' => 'pending',
            'processing' => 'processing', 
            'on-hold' => 'pending',        // Map on-hold to pending
            'completed' => 'processing',   // Map completed to processing (ready to ship)
            'cancelled' => 'cancelled',
            'refunded' => 'cancelled',     // Map refunded to cancelled
            'failed' => 'failed',
            'checkout-draft' => 'pending',
            'auto-draft' => 'pending',
            'trash' => 'cancelled',
            
            // Common custom statuses
            'ready-to-ship' => 'ready_to_ship',
            'ready_to_ship' => 'ready_to_ship',
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            'returned' => 'returned',
            'partially-shipped' => 'processing',
            'backorder' => 'pending',
            'pre-order' => 'pending',
            
            // Greek statuses (common in Greek e-commerce)
            'σε αναμονή' => 'pending',
            'σε επεξεργασία' => 'processing',
            'ολοκληρωμένη' => 'processing',
            'ακυρωμένη' => 'cancelled',
            'αποστολή' => 'shipped',
            'παραδόθηκε' => 'delivered',
            'επιστροφή' => 'returned',
            
            // Default fallback
            'default' => 'pending'
        ];
        
        $normalizedStatus = strtolower(trim($wooStatus));
        $mappedStatus = $statusMapping[$normalizedStatus] ?? $statusMapping['default'];
        
        // Log status mapping for debugging
        if ($normalizedStatus !== $mappedStatus) {
            \Log::info('Order status mapped', [
                'original_status' => $wooStatus,
                'normalized_status' => $normalizedStatus,
                'mapped_status' => $mappedStatus
            ]);
        }
        
        return $mappedStatus;
    }

    /**
     * Add custom status mapping (can be called from WordPress plugin if needed)
     */
    public function addStatusMapping(string $wooStatus, string $internalStatus): void
    {
        // This could be extended to store custom mappings in database
        // For now, it's handled in the mapOrderStatus method
        \Log::info('Custom status mapping requested', [
            'woo_status' => $wooStatus,
            'internal_status' => $internalStatus
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Log incoming request
        \Log::info('WooCommerce order received', [
            'headers' => $request->headers->all(),
            'payload' => $request->all()
        ]);

        // Read headers
        $headerKey = $request->header('X-Api-Key');
        $tenantId  = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');

        if (!$headerKey) {
            \Log::warning('WooCommerce order rejected: No API key provided');
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            \Log::warning('WooCommerce order rejected: Invalid tenant ID', ['tenant_id' => $tenantId]);
            return response()->json(['success' => false, 'message' => 'Invalid tenant'], 422);
        }

        // Accept global bridge key OR tenant-specific token
        $globalKey = (string) config('services.dm_bridge.key');
        $isGlobalKeyValid = $globalKey && hash_equals($globalKey, (string) $headerKey);
        $isTenantTokenValid = $tenant->isApiTokenValid((string) $headerKey);

        if (!$isGlobalKeyValid && !$isTenantTokenValid) {
            \Log::warning('WooCommerce order rejected: Invalid API key', [
                'tenant_id' => $tenantId,
                'api_key_provided' => !empty($headerKey),
                'global_key_valid' => $isGlobalKeyValid,
                'tenant_token_valid' => $isTenantTokenValid
            ]);
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Validate payload
        $v = Validator::make($request->all(), [
            'source'                              => 'required|in:woocommerce',
            'order.external_order_id'             => 'required|string',
            'order.total_amount'                  => 'required|numeric|min:0',
            'shipping.address.address_1'          => 'required|string',
            'shipping.address.city'               => 'required|string',
            'shipping.address.postcode'           => 'required|string',
            'customer.email'                      => 'nullable|email',
        ]);

        if ($v->fails()) {
            \Log::error('WooCommerce order validation failed', [
                'errors' => $v->errors()->toArray(),
                'request_data' => $request->all(),
                'tenant_id' => $tenantId
            ]);
            return response()->json(['success'=>false, 'message'=>'Validation failed', 'errors'=>$v->errors()], 422);
        }

        // If order already exists, update customer info and return it (idempotency)
        $externalId = data_get($request, 'order.external_order_id');
        
        // Use a single transaction for the entire process to prevent race conditions
        try {
            $result = \DB::transaction(function () use ($tenant, $externalId, $request) {
                // First, try to find existing order
                $existing = Order::where('tenant_id', $tenant->id)
                    ->where('external_order_id', $externalId)
                    ->first();
                    
                \Log::info('Checked for existing order', [
                    'external_order_id' => $externalId,
                    'tenant_id' => $tenant->id,
                    'existing_order_id' => $existing?->id
                ]);

                if ($existing) {
                    // Update customer information if it's missing
                    if (empty($existing->customer_name) || empty($existing->customer_email)) {
                        // Get customer data from request for update
                        $customerName = trim(implode(' ', array_filter([
                            data_get($request, 'customer.first_name'),
                            data_get($request, 'customer.last_name'),
                        ])));
                        $customerEmail = data_get($request, 'customer.email') ?: Str::uuid().'@no-email.local';
                        $customerPhone = data_get($request, 'customer.phone');
                        
                        $existing->update([
                            'customer_name'  => $customerName,
                            'customer_email' => $customerEmail,
                            'customer_phone' => $customerPhone,
                        ]);
                        \Log::info('Updated customer information for existing order', [
                            'order_id' => $existing->id,
                            'customer_name' => $customerName,
                            'customer_email' => $customerEmail
                        ]);
                    }
                    
                    \Log::info('Order already exists, returning existing order', [
                        'external_order_id' => $externalId,
                        'order_id' => $existing->id,
                        'tenant_id' => $tenant->id
                    ]);
                    
                    return ['order' => $existing, 'shipment' => null, 'existing' => true];
                }

                \Log::info('Creating new order', [
                    'external_order_id' => $externalId,
                    'tenant_id' => $tenant->id
                ]);

                // Find or create customer by email OR phone
                $customerEmail = data_get($request, 'customer.email') ?: Str::uuid().'@no-email.local';
                $customerPhone = data_get($request, 'customer.phone');
                $customerName = trim(implode(' ', array_filter([
                    data_get($request, 'customer.first_name'),
                    data_get($request, 'customer.last_name'),
                ])));

                // Check for existing customer by email OR phone
                $customer = Customer::where('tenant_id', $tenant->id)
                    ->where(function ($query) use ($customerEmail, $customerPhone) {
                        $query->where('email', $customerEmail);
                        if ($customerPhone) {
                            $query->orWhere('phone', $customerPhone);
                        }
                    })
                    ->first();

                if (!$customer) {
                    // Create new customer
                    $customer = Customer::create([
                        'tenant_id' => $tenant->id,
                        'name' => $customerName,
                        'email' => $customerEmail,
                        'phone' => $customerPhone,
                    ]);
                    \Log::info('Created new customer', [
                        'customer_id' => $customer->id,
                        'customer_name' => $customerName,
                        'customer_email' => $customerEmail
                    ]);
                } else {
                    // Update existing customer with new information if provided
                    $updateData = [];
                    if ($customerName && $customerName !== $customer->name) {
                        $updateData['name'] = $customerName;
                    }
                    if ($customerPhone && $customerPhone !== $customer->phone) {
                        $updateData['phone'] = $customerPhone;
                    }
                    if ($customerEmail !== $customer->email) {
                        $updateData['email'] = $customerEmail;
                    }

                    if (!empty($updateData)) {
                        $customer->update($updateData);
                    }
                    \Log::info('Using existing customer', [
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'customer_email' => $customer->email
                    ]);
                }

                // Ensure customer_id is valid
                if (!$customer || !$customer->id) {
                    \Log::error('Customer creation failed or customer ID is null', [
                        'customer' => $customer,
                        'customer_id' => $customer?->id
                    ]);
                    throw new \Exception('Failed to create or find customer');
                }

                // Create the order data
                $orderData = [
                    'tenant_id'        => $tenant->id,
                    'external_order_id'=> $externalId,
                    'order_number'     => data_get($request, 'order.order_number'),
                    'status'           => $this->mapOrderStatus(data_get($request, 'order.status', 'pending')),
                    'total_amount'     => (float) data_get($request, 'order.total_amount', 0),
                    'subtotal'         => (float) data_get($request, 'order.subtotal', 0),
                    'tax_amount'       => (float) data_get($request, 'order.tax_amount', 0),
                    'shipping_cost'    => (float) data_get($request, 'order.shipping_cost', 0),
                    'discount_amount'  => (float) data_get($request, 'order.discount_amount', 0),
                    'currency'         => data_get($request, 'order.currency', 'EUR'),
                    'payment_status'   => data_get($request, 'order.payment_status', 'pending'),
                    'payment_method'   => data_get($request, 'order.payment_method'),
                    'customer_id'      => $customer->id,
                    
                    // Customer Information (populate for admin panel display)
                    'customer_name'    => $customer->name,
                    'customer_email'   => $customer->email,
                    'customer_phone'   => $customer->phone,
                    
                    // Shipping address
                    'shipping_address'     => data_get($request, 'shipping.address.address_1'),
                    'shipping_city'        => data_get($request, 'shipping.address.city'),
                    'shipping_postal_code' => data_get($request, 'shipping.address.postcode'),
                    'shipping_country'     => data_get($request, 'shipping.address.country', 'GR'),
                ];

                \Log::info('Creating order with data', [
                    'external_order_id' => $externalId,
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'order_data' => $orderData
                ]);

                try {
                    // Try to create the order
                    $order = Order::create($orderData);
                    \Log::info('Order created successfully', [
                        'order_id' => $order->id,
                        'external_order_id' => $externalId
                    ]);
                } catch (\Illuminate\Database\QueryException $createException) {
                    // If creation fails due to duplicate, try to find the existing order
                    if ($createException->getCode() == 23000 && str_contains($createException->getMessage(), 'Duplicate entry')) {
                        \Log::info('Order creation failed due to duplicate, fetching existing order', [
                            'external_order_id' => $externalId,
                            'tenant_id' => $tenant->id,
                            'error' => $createException->getMessage()
                        ]);
                        
                        // Try to find the order that was created by another request
                        $order = Order::where('tenant_id', $tenant->id)
                            ->where('external_order_id', $externalId)
                            ->first();
                        
                        if (!$order) {
                            // Try without tenant filter as fallback
                            $order = Order::where('external_order_id', $externalId)->first();
                        }
                        
                        if ($order) {
                            // Update the existing order with new data
                            $order->update($orderData);
                            \Log::info('Found and updated existing order after race condition', [
                                'order_id' => $order->id,
                                'external_order_id' => $externalId
                            ]);
                        } else {
                            \Log::error('Could not find existing order after duplicate constraint', [
                                'external_order_id' => $externalId,
                                'tenant_id' => $tenant->id,
                                'available_orders' => Order::where('external_order_id', $externalId)->get(['id', 'tenant_id', 'external_order_id'])
                            ]);
                            throw $createException; // Re-throw if we still can't find the order
                        }
                    } else {
                        \Log::error('Order creation failed with non-duplicate error', [
                            'external_order_id' => $externalId,
                            'tenant_id' => $tenant->id,
                            'error' => $createException->getMessage(),
                            'error_code' => $createException->getCode()
                        ]);
                        throw $createException; // Re-throw if it's not a duplicate error
                    }
                }
                
                // Create order items if provided
                $this->createOrderItems($order, $request);
                
                // Create shipment (default true)
                $shipment = null;
                if ($request->boolean('create_shipment', true)) {
                    \Log::info('Looking for courier', [
                        'tenant_id' => $tenant->id,
                        'order_id' => $order->id
                    ]);
                    
                    $courier = Courier::where('tenant_id', $tenant->id)
                        ->where('is_default', true)
                        ->first() ?? Courier::where('tenant_id', $tenant->id)->first();

                    if (!$courier) {
                        \Log::error('WooCommerce order failed: No courier configured for tenant', [
                            'tenant_id' => $tenant->id,
                            'order_id' => $order->id,
                            'available_couriers' => Courier::where('tenant_id', $tenant->id)->get(['id', 'name', 'is_default'])
                        ]);
                        throw new \Exception('No courier configured for tenant');
                    }
                    
                    \Log::info('Found courier', [
                        'courier_id' => $courier->id,
                        'courier_name' => $courier->name
                    ]);

                    $addr = $request->input('shipping.address');

                    // collision-safe tracking number generation
                    $tracking = null;
                    do {
                        $tracking = strtoupper(Str::random(12));
                    } while (
                        Shipment::where('tenant_id', $tenant->id)
                            ->where('tracking_number', $tracking)
                            ->exists()
                    );

                    $shipment = Shipment::create([
                        'tenant_id'       => $tenant->id,
                        'order_id'        => $order->id,
                        'courier_id'      => $courier->id,
                        'tracking_number' => $tracking,
                        'status'          => 'pending',
                        'recipient_name'  => trim(implode(' ', array_filter([
                            $addr['first_name'] ?? '',
                            $addr['last_name'] ?? ''
                        ]))),
                        'recipient_phone' => $addr['phone'] ?? '',
                        'recipient_email' => $addr['email'] ?? '',
                        'address_line_1'  => $addr['address_1'] ?? '',
                        'address_line_2'  => $addr['address_2'] ?? '',
                        'city'            => $addr['city'] ?? '',
                        'postal_code'     => $addr['postcode'] ?? '',
                        'country'         => $addr['country'] ?? 'GR',
                        'weight'          => (float) data_get($request, 'shipping.weight', 0),
                        'dimensions'      => null,
                        'special_instructions' => null,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                    
                    \Log::info('Shipment created successfully', [
                        'shipment_id' => $shipment->id,
                        'tracking_number' => $tracking
                    ]);
                }
                
                \Log::info('Transaction completed successfully', [
                    'order_id' => $order->id,
                    'shipment_id' => $shipment?->id
                ]);
                
                return ['order' => $order, 'shipment' => $shipment];
            });
            
            $order = $result['order'];
            $shipment = $result['shipment'];
            
            // Check if this was an existing order
            if (isset($result['existing']) && $result['existing']) {
                return response()->json([
                    'success'     => true,
                    'message'     => 'Order already exists',
                    'order_id'    => $order->id,
                    'shipment_id' => Shipment::where('order_id', $order->id)->value('id'),
                ], 200);
            }
        } catch (\Exception $e) {
            \Log::error('Transaction failed with unexpected error', [
                'external_order_id' => $externalId,
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
            throw $e;
        }

        \Log::info('WooCommerce order processed successfully', [
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'shipment_id' => $shipment?->id,
            'external_order_id' => $externalId
        ]);

        return response()->json([
            'success'     => true,
            'message'     => 'Received',
            'order_id'    => $order->id,
            'shipment_id' => $shipment?->id,
        ], 201);
    }

    private function formatAddress($a): string
    {
        if (!$a) return '';
        $parts = [
            $a['first_name'] ?? null,
            $a['last_name'] ?? null,
            $a['company'] ?? null,
            $a['address_1'] ?? null,
            $a['address_2'] ?? null,
            $a['city'] ?? null,
            $a['postcode'] ?? null,
            $a['country'] ?? null,
            $a['phone'] ?? null,
            $a['email'] ?? null,
        ];
        return implode(', ', array_filter($parts));
    }
    
    /**
     * Create order items from request data
     */
    private function createOrderItems($order, $request)
    {
        $items = data_get($request, 'order.items', []);
        
        if (empty($items)) {
            // If no items provided, create a generic item based on order total
            $this->createGenericOrderItem($order, $request);
            return;
        }
        
        foreach ($items as $itemData) {
            // Handle product images
            $productImages = [];
            $imageUrl = data_get($itemData, 'image_url');
            if ($imageUrl) {
                $productImages[] = $imageUrl;
                \Log::info('Product image added to order item', [
                    'product_name' => data_get($itemData, 'name'),
                    'image_url' => $imageUrl
                ]);
            }
            
            OrderItem::create([
                'order_id' => $order->id,
                'tenant_id' => $order->tenant_id,
                'product_sku' => data_get($itemData, 'sku', 'N/A'),
                'product_name' => data_get($itemData, 'name', 'Product'),
                'quantity' => (int) data_get($itemData, 'quantity', 1),
                'unit_price' => (float) data_get($itemData, 'price', 0),
                'final_unit_price' => (float) data_get($itemData, 'price', 0),
                'total_price' => (float) data_get($itemData, 'total', 0),
                'weight' => (float) data_get($itemData, 'weight', 0),
                'is_digital' => false,
                'is_fragile' => false,
                'external_product_id' => data_get($itemData, 'product_id', null),
                'variation_id' => data_get($itemData, 'variation_id', null),
                'product_images' => $productImages,
            ]);
        }
    }
    
    /**
     * Create a generic order item when no specific items are provided
     */
    private function createGenericOrderItem($order, $request)
    {
        $totalAmount = (float) data_get($request, 'order.total_amount', 0);
        $subtotal = (float) data_get($request, 'order.subtotal', $totalAmount);
        
        OrderItem::create([
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
            'product_sku' => 'ORDER-' . $order->external_order_id,
            'product_name' => 'Order Items',
            'quantity' => 1,
            'unit_price' => $subtotal,
            'final_unit_price' => $subtotal,
            'total_price' => $subtotal,
            'weight' => (float) data_get($request, 'shipping.weight', 0),
            'is_digital' => false,
            'is_fragile' => false,
        ]);
        
        \Log::info('Created generic order item', [
            'order_id' => $order->id,
            'total_amount' => $totalAmount,
            'subtotal' => $subtotal
        ]);
    }

    /**
     * Update existing order with latest data (sync)
     */
    public function update(Request $request): JsonResponse
    {
        // Log incoming sync request
        \Log::info('WooCommerce order sync received', [
            'headers' => $request->headers->all(),
            'payload' => $request->all()
        ]);

        // Read headers
        $headerKey = $request->header('X-Api-Key');
        $tenantId  = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');

        if (!$headerKey) {
            return response()->json(['success' => false, 'message' => 'API key required'], 401);
        }

        // Validate tenant
        $tenant = Tenant::where('api_key', $headerKey)->first();
        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Invalid API key'], 401);
        }

        // Get the DMM order ID from the request
        $dmmOrderId = $request->input('dmm_order_id');
        if (!$dmmOrderId) {
            return response()->json(['success' => false, 'message' => 'DMM order ID required for sync'], 400);
        }

        // Find the existing order
        $order = Order::where('id', $dmmOrderId)
                     ->where('tenant_id', $tenant->id)
                     ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Update order data
        $order->update([
            'status' => $this->mapOrderStatus(data_get($request, 'order.status', $order->status)),
            'total_amount' => (float) data_get($request, 'order.total_amount', $order->total_amount),
            'subtotal' => (float) data_get($request, 'order.subtotal', $order->subtotal),
            'tax_amount' => (float) data_get($request, 'order.tax_amount', $order->tax_amount),
            'shipping_cost' => (float) data_get($request, 'order.shipping_cost', $order->shipping_cost),
            'discount_amount' => (float) data_get($request, 'order.discount_amount', $order->discount_amount),
            'payment_status' => data_get($request, 'order.payment_status', $order->payment_status),
            'payment_method' => data_get($request, 'order.payment_method', $order->payment_method),
        ]);

        // Update order items with latest data (including images)
        $this->updateOrderItems($order, $request);

        \Log::info('WooCommerce order synced successfully', [
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'external_order_id' => $order->external_order_id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order synced successfully',
            'data' => [
                'success' => true,
                'message' => 'Order synced successfully',
                'order_id' => $order->id,
                'shipment_id' => $order->shipment_id
            ]
        ], 200);
    }

    /**
     * Update order items with latest data
     */
    private function updateOrderItems($order, $request)
    {
        $items = data_get($request, 'order.items', []);
        
        if (empty($items)) {
            return;
        }

        // Delete existing order items
        $order->orderItems()->delete();

        // Create updated order items
        foreach ($items as $itemData) {
            // Handle product images
            $productImages = [];
            $imageUrl = data_get($itemData, 'image_url');
            if ($imageUrl) {
                $productImages[] = $imageUrl;
                \Log::info('Product image updated in order item', [
                    'product_name' => data_get($itemData, 'name'),
                    'image_url' => $imageUrl
                ]);
            }

            OrderItem::create([
                'order_id' => $order->id,
                'tenant_id' => $order->tenant_id,
                'product_sku' => data_get($itemData, 'sku', 'N/A'),
                'product_name' => data_get($itemData, 'name', 'Product'),
                'quantity' => (int) data_get($itemData, 'quantity', 1),
                'unit_price' => (float) data_get($itemData, 'price', 0),
                'final_unit_price' => (float) data_get($itemData, 'price', 0),
                'total_price' => (float) data_get($itemData, 'total', 0),
                'weight' => (float) data_get($itemData, 'weight', 0),
                'is_digital' => false,
                'is_fragile' => false,
                'external_product_id' => data_get($itemData, 'product_id', null),
                'variation_id' => data_get($itemData, 'variation_id', null),
                'product_images' => $productImages,
            ]);
        }
    }
}

