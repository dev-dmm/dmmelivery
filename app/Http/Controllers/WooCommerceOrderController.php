<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Contracts\Cache\LockTimeoutException;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\Courier;
use App\Support\HmacVerifier;

class WooCommerceOrderController extends Controller
{
    public function __construct(private HmacVerifier $verifier) {}
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
        
        // Log status mapping for debugging (only when status changes and not default)
        if ($normalizedStatus !== $mappedStatus && $mappedStatus !== 'pending') {
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
        // Sanitize headers for logging (don't log secrets)
        $sanitizedHeaders = collect($request->headers->all())
            ->except(['x-api-key', 'authorization', 'x-payload-signature', 'x-timestamp', 'x-nonce'])
            ->all();
        
        // Log incoming request (without sensitive data)
        \Log::info('WooCommerce order received', [
            'headers' => $sanitizedHeaders,
            'has_payload' => !empty($request->all())
        ]);
        
        // Normalize external order ID
        $externalId = trim((string) data_get($request, 'order.external_order_id'));
        if ($externalId === '') {
            \Log::warning('Rejected: empty external_order_id after normalization');
            return response()->json(['success'=>false, 'message'=>'Invalid external_order_id'], 422);
        }
        $tenantId = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');
        
        \Log::info('Starting order processing with Redis lock', [
            'external_order_id' => $externalId,
            'tenant_id' => $tenantId,
            'timestamp' => now()->toDateTimeString()
        ]);
        
        // Use Redis lock to prevent race conditions
        // Validate that cache driver supports atomic locks
        $store = \Cache::getStore();
        if (! $store instanceof \Illuminate\Contracts\Cache\LockProvider) {
            \Log::error('Atomic locks unsupported by current cache driver. Use Redis/Memcached/Database.');
            return response()->json(['success' => false, 'message' => 'Server misconfigured'], 500);
        }
        
        $lockKey = "orders:create:{$tenantId}:{$externalId}";
        
        try {
            return \Cache::lock($lockKey, 10)->block(5, function () use ($request, $externalId, $tenantId) {
                return $this->doStore($request, $externalId, $tenantId);
            });
        } catch (LockTimeoutException $e) {
            \Log::warning('Order lock timeout', compact('lockKey','tenantId','externalId'));
            return response()->json(['success'=>false,'message'=>'Busy, try again'], 423);
        }
    }
    
    private function doStore(Request $request, string $externalId, string $tenantId): JsonResponse
    {
        \Log::info('Inside Redis lock - starting order creation', [
            'external_order_id' => $externalId,
            'tenant_id' => $tenantId,
            'timestamp' => now()->toDateTimeString()
        ]);

        // Get tenant from middleware (already validated)
        $tenant = $request->attributes->get('tenant');
        if (!$tenant) {
            // Fallback: find tenant if middleware didn't set it (shouldn't happen)
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                \Log::error('Tenant not found in request attributes or by ID', ['tenant_id' => $tenantId]);
                return response()->json(['success' => false, 'message' => 'Invalid tenant'], 422);
            }
        }

        // Validate payload
        $v = Validator::make($request->all(), [
            'source'                              => 'required|in:woocommerce',
            'order.external_order_id'             => 'required|string',
            'order.total_amount'                  => 'required|numeric|min:0',
            'shipping.address.address_1'          => 'required|string',
            'shipping.address.city'               => 'required|string',
            'shipping.address.postcode'           => 'required|string',
            'customer.email'                      => 'nullable|string|max:255',
        ]);

        if ($v->fails()) {
            // Mask PII in logs
            $clean = $request->all();
            data_set($clean, 'customer.email', '[redacted]');
            data_set($clean, 'customer.phone', '[redacted]');
            
            \Log::error('WooCommerce order validation failed', [
                'errors' => $v->errors()->toArray(),
                'request_data' => $clean,
                'tenant_id' => $tenantId
            ]);
            return response()->json(['success'=>false, 'message'=>'Validation failed', 'errors'=>$v->errors()], 422);
        }

        // $externalId is already normalized & locked, don't overwrite it
        
        \Log::info('Starting order processing', [
            'external_order_id' => $externalId,
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name ?? 'Unknown',
            'request_source' => data_get($request, 'source'),
            'order_number' => data_get($request, 'order.order_number'),
            'total_amount' => data_get($request, 'order.total_amount'),
            'customer_email' => data_get($request, 'customer.email'),
            'customer_name' => trim(implode(' ', array_filter([
                data_get($request, 'customer.first_name'),
                data_get($request, 'customer.last_name'),
            ]))),
            'voucher_number' => data_get($request, 'voucher_number'),
            'courier_company' => data_get($request, 'courier_company')
        ]);
        
        // Set READ COMMITTED isolation level before transaction starts (MySQL-specific)
        try {
            \DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        } catch (\Throwable $e) {
            \Log::notice('Skipping isolation level set (driver not supported)', [
                'driver' => \DB::getDriverName(),
                'error' => $e->getMessage()
            ]);
        }
        
        // Use a single transaction for the entire process to prevent race conditions
        try {
            $result = \DB::transaction(function () use ($tenant, $externalId, $request) {
                
                // First, try to find existing order (including trashed orders)
                // Use withoutGlobalScopes() to remove ALL global scopes, then manually filter
                $existing = Order::withoutGlobalScopes()
                    ->withTrashed()
                    ->where('tenant_id', $tenant->id)
                    ->where('external_order_id', $externalId)
                    ->first();
                    
                \Log::info('Checked for existing order', [
                    'external_order_id' => $externalId,
                    'tenant_id' => $tenant->id,
                    'existing_order_id' => $existing?->id,
                    'found_existing' => $existing ? 'YES' : 'NO',
                    'is_trashed' => $existing?->trashed() ? 'YES' : 'NO',
                    'deleted_at' => $existing?->deleted_at
                ]);

                if ($existing) {
                    // If order is trashed, restore it
                    if ($existing->trashed()) {
                        \Log::info('Found trashed order; restoring', [
                            'order_id' => $existing->id,
                            'external_order_id' => $externalId,
                            'tenant_id' => $tenant->id,
                            'deleted_at' => $existing->deleted_at
                        ]);

                        $existing->restore();

                        // Update order with new data from request
                        $existing->update([
                            'status' => $this->mapOrderStatus(data_get($request, 'order.status', 'pending')),
                            'total_amount' => (float) data_get($request, 'order.total_amount', 0),
                            'subtotal' => (float) data_get($request, 'order.subtotal', 0),
                            'tax_amount' => (float) data_get($request, 'order.tax_amount', 0),
                            'shipping_cost' => (float) data_get($request, 'order.shipping_cost', 0),
                            'discount_amount' => (float) data_get($request, 'order.discount_amount', 0),
                            'currency' => data_get($request, 'order.currency', 'EUR'),
                            'payment_status' => data_get($request, 'order.payment_status', 'pending'),
                            'payment_method' => data_get($request, 'order.payment_method'),
                            'customer_name' => trim(implode(' ', array_filter([
                                data_get($request, 'customer.first_name'),
                                data_get($request, 'customer.last_name'),
                            ]))),
                            'customer_email' => data_get($request, 'customer.email') ?: ('order-'.$externalId.'@no-email.local'),
                            'customer_phone' => data_get($request, 'customer.phone'),
                            'shipping_address' => data_get($request, 'shipping.address.address_1'),
                            'shipping_city' => data_get($request, 'shipping.address.city'),
                            'shipping_postal_code' => data_get($request, 'shipping.address.postcode'),
                            'shipping_country' => data_get($request, 'shipping.address.country', 'GR'),
                        ]);

                        \Log::info('Trashed order restored and updated', [
                            'order_id' => $existing->id,
                            'external_order_id' => $externalId,
                            'tenant_id' => $tenant->id
                        ]);

                        return ['order' => $existing, 'shipment' => null, 'existing' => true];
                    }

                    // Active order - return idempotently
                    \Log::info('Found active order; returning existing', [
                        'order_id' => $existing->id,
                        'external_order_id' => $externalId,
                        'tenant_id' => $tenant->id
                    ]);

                    return ['order' => $existing, 'shipment' => null, 'existing' => true];
                }


                // Find or create customer by email OR phone
                $customerEmail = data_get($request, 'customer.email') ?: ('order-'.$externalId.'@no-email.local');
                $customerPhone = data_get($request, 'customer.phone');
                $customerName = trim(implode(' ', array_filter([
                    data_get($request, 'customer.first_name'),
                    data_get($request, 'customer.last_name'),
                ])));

                // Check for existing customer by email OR phone
                \Log::info('Looking for existing customer', [
                    'tenant_id' => $tenant->id,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'customer_name' => $customerName
                ]);
                
                $customer = Customer::where('tenant_id', $tenant->id)
                    ->where(function ($query) use ($customerEmail, $customerPhone) {
                        $query->where('email', $customerEmail);
                        if ($customerPhone) {
                            $query->orWhere('phone', $customerPhone);
                        }
                    })
                    ->first();
                    
                \Log::info('Customer lookup result', [
                    'customer_found' => $customer ? 'YES' : 'NO',
                    'customer_id' => $customer?->id,
                    'customer_email' => $customer?->email,
                    'customer_phone' => $customer?->phone
                ]);

                if (!$customer) {
                    // Find or create global customer
                    $globalCustomerService = app(\App\Services\GlobalCustomerService::class);
                    $globalCustomer = $globalCustomerService->findOrCreateGlobalCustomer($customerEmail, $customerPhone);
                    
                    // Create new customer
                    \Log::info('Creating new customer', [
                        'tenant_id' => $tenant->id,
                        'customer_name' => $customerName,
                        'customer_email' => $customerEmail,
                        'customer_phone' => $customerPhone,
                        'global_customer_id' => $globalCustomer->id
                    ]);
                    
                    $customer = Customer::create([
                        'tenant_id' => $tenant->id,
                        'global_customer_id' => $globalCustomer->id,
                        'name' => $customerName,
                        'email' => $customerEmail,
                        'phone' => $customerPhone,
                    ]);
                    
                    \Log::info('Created new customer successfully', [
                        'customer_id' => $customer->id,
                        'customer_name' => $customerName,
                        'customer_email' => $customerEmail,
                        'customer_phone' => $customerPhone
                    ]);
                } else {
                    // Ensure global customer is linked
                    if (!$customer->global_customer_id) {
                        $globalCustomerService = app(\App\Services\GlobalCustomerService::class);
                        $globalCustomer = $globalCustomerService->findOrCreateGlobalCustomer($customerEmail, $customerPhone);
                        $customer->update(['global_customer_id' => $globalCustomer->id]);
                    }
                    
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
                    
                    // Voucher and courier information (stored in additional_data)
                    'additional_data'      => array_filter([
                        'voucher_number' => data_get($request, 'voucher_number'),
                        'courier_company' => data_get($request, 'courier_company'),
                    ]),
                ];

                \Log::info('Creating order with data', [
                    'external_order_id' => $externalId,
                    'tenant_id' => $tenant->id,
                    'order_data' => $orderData
                ]);

                // Create the order (Redis lock prevents race conditions)
                $order = Order::create($orderData);
                \Log::info('Order created successfully', [
                    'order_id' => $order->id,
                    'external_order_id' => $externalId,
                    'tenant_id' => $tenant->id
                ]);
                
                // Create order items if provided
                $this->createOrderItems($order, $request);
                
                // Create shipment ONLY if order has a voucher
                // If no voucher, order is created but shipment waits for voucher to be added
                $shipment = null;
                $shipmentWarning = null;
                $courier = null;
                
                $courierCompany = data_get($request, 'courier_company');
                $voucherNumber = data_get($request, 'voucher_number');
                
                // Only create shipment if voucher exists
                if (!empty($voucherNumber) && !empty($courierCompany)) {
                    \Log::info('Order has voucher - creating shipment', [
                        'tenant_id' => $tenant->id,
                        'order_id' => $order->id,
                        'order_external_id' => $externalId,
                        'voucher_number' => $voucherNumber,
                        'courier_company' => $courierCompany
                    ]);
                    
                    // Find courier by name (case-insensitive) or code
                    $courier = Courier::where('tenant_id', $tenant->id)
                        ->where(function($query) use ($courierCompany) {
                            $query->whereRaw('LOWER(name) = ?', [strtolower($courierCompany)])
                                  ->orWhereRaw('LOWER(code) = ?', [strtolower($courierCompany)]);
                        })
                        ->first();
                    
                    if ($courier) {
                        \Log::info('Found courier from voucher', [
                            'courier_company' => $courierCompany,
                            'courier_id' => $courier->id,
                            'courier_name' => $courier->name,
                            'courier_code' => $courier->code,
                            'voucher_number' => $voucherNumber
                        ]);
                    } else {
                        \Log::error('Courier from voucher not found in database - shipment will be skipped', [
                            'courier_company' => $courierCompany,
                            'tenant_id' => $tenant->id,
                            'voucher_number' => $voucherNumber,
                            'available_couriers' => Courier::where('tenant_id', $tenant->id)->get(['id', 'name', 'code'])
                        ]);
                        // Don't create shipment if voucher courier not found - this is a configuration error
                        $shipmentWarning = "Order has voucher for '{$courierCompany}' but this courier is not configured in the system. Please configure the courier and create shipment manually.";
                    }
                } else {
                    // No voucher - order created but shipment waits for voucher
                    \Log::info('Order created without voucher - shipment will be created when voucher is added', [
                        'tenant_id' => $tenant->id,
                        'order_id' => $order->id,
                        'order_external_id' => $externalId,
                        'has_voucher_number' => !empty($voucherNumber),
                        'has_courier_company' => !empty($courierCompany)
                    ]);
                    // No shipment created - this is expected behavior
                }
                
                // Only create shipment if voucher exists AND courier was found
                if (!empty($voucherNumber) && !empty($courierCompany) && $courier) {
                    $addr = $request->input('shipping.address');
                
                    \Log::info('Preparing shipment creation', [
                        'tenant_id' => $tenant->id,
                        'order_id' => $order->id,
                        'customer_id' => $customer->id,
                        'courier_id' => $courier->id,
                        'courier_name' => $courier->name,
                        'voucher_number' => $voucherNumber,
                        'shipping_address' => $this->formatAddress($addr),
                        'shipping_city' => $addr['city'] ?? '',
                        'weight' => (float) data_get($request, 'shipping.weight', 0.5),
                        'shipping_cost' => (float) data_get($request, 'order.shipping_cost', 0)
                    ]);

                    // Use voucher number as tracking number
                    $tracking = trim($voucherNumber);
                    
                    \Log::info('Using voucher number as tracking number', [
                        'voucher_number' => $tracking,
                        'courier_company' => $courierCompany
                    ]);

                    $shipment = Shipment::create([
                        'tenant_id'         => $tenant->id,
                        'order_id'          => $order->id,
                        'customer_id'       => $customer->id,
                        'global_customer_id' => $customer->global_customer_id ?? null,
                        'courier_id'        => $courier->id,
                        'tracking_number'   => $tracking,
                        'courier_tracking_id' => $tracking, // Voucher number is the tracking number
                        'status'            => 'pending',
                        'shipping_address'  => $this->formatAddress($addr),
                        'shipping_city'     => $addr['city'] ?? '',
                        'billing_address'   => $this->formatAddress($addr),
                        'weight'            => (float) data_get($request, 'shipping.weight', 0.5),
                        'shipping_cost'     => (float) data_get($request, 'order.shipping_cost', 0),
                        'dimensions'        => null,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                    
                    \Log::info('Shipment created successfully with voucher', [
                        'shipment_id' => $shipment->id,
                        'tracking_number' => $tracking,
                        'voucher_number' => $voucherNumber,
                        'courier_company' => $courierCompany,
                        'courier_id' => $courier->id
                    ]);
                }
                
                \Log::info('Transaction completed successfully', [
                    'order_id' => $order->id,
                    'shipment_id' => $shipment?->id,
                    'has_warning' => !empty($shipmentWarning)
                ]);
                
                return ['order' => $order, 'shipment' => $shipment, 'warning' => $shipmentWarning];
            });
            
            $order = $result['order'];
            $shipment = $result['shipment'];
            $warning = $result['warning'] ?? null;
            
            \Log::info('Transaction completed successfully', [
                'external_order_id' => $externalId,
                'tenant_id' => $tenant->id,
                'order_id' => $order->id,
                'shipment_id' => $shipment?->id,
                'tracking_number' => $shipment?->tracking_number,
                'is_existing_order' => isset($result['existing']) && $result['existing'],
                'has_warning' => !empty($warning)
            ]);
            
            // Check if this was an existing order
            if (isset($result['existing']) && $result['existing']) {
                \Log::info('Returning existing order response', [
                    'order_id' => $order->id,
                    'shipment_id' => Shipment::where('order_id', $order->id)->value('id')
                ]);
                
                return response()->json([
                    'success'     => true,
                    'message'     => 'Order already exists',
                    'order_id'    => $order->id,
                    'shipment_id' => Shipment::where('order_id', $order->id)->value('id'),
                ], 200);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('DB error on order create', [
                'class'       => get_class($e),
                'sql_state'   => $e->errorInfo[0] ?? null,
                'driver_code' => $e->errorInfo[1] ?? null,
                'driver_msg'  => $e->errorInfo[2] ?? $e->getMessage(),
                'external_id' => $externalId,
                'tenant_id'   => $tenant->id,
            ]);

            // Check if this is a duplicate key error
            // Safer duplicate-key detection using SQLSTATE and constraint name
            if ($e instanceof \Illuminate\Database\QueryException) {
                $isUnique = $e->getCode() === '23000' 
                    && str_contains($e->getMessage(), 'orders_tenant_extid_unique');

                if ($isUnique) {
                    \Log::info('Order already exists (unique constraint violation)', [
                        'external_order_id' => $externalId,
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage()
                    ]);

                    // Try to find the existing order
                    $existing = Order::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('external_order_id', $externalId)
                        ->first();

                    if ($existing) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Order already exists',
                            'order_id' => $existing->id,
                            'shipment_id' => Shipment::where('order_id', $existing->id)->value('id'),
                        ], 200);
                    }
                }
            }

            throw $e;
        } catch (\Throwable $e) {
            \Log::error('Unexpected error', [
                'class'       => get_class($e),
                'message'     => $e->getMessage(),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
                'external_id' => $externalId ?? null,
                'tenant_id'   => $tenant->id ?? null,
            ]);
            throw $e;
        } finally {
            // Reset to default isolation level (MySQL default: REPEATABLE READ)
            try {
                \DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            } catch (\Throwable $e) {
                // Ignore - driver may not support this
            }
        }

        \Log::info('WooCommerce order processed successfully', [
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'shipment_id' => $shipment?->id,
            'external_order_id' => $externalId,
            'has_warning' => !empty($warning)
        ]);

        $response = [
            'success'     => true,
            'message'     => $warning ?: 'Received',
            'order_id'    => $order->id,
            'shipment_id' => $shipment?->id,
        ];
        
        // Include warning if present
        if ($warning) {
            $response['warning'] = $warning;
        }

        return response()->json($response, 201);
    }

    /**
     * Verify HMAC payload signature with replay protection
     * 
     * Delegates to HmacVerifier service
     *
     * @param Request $request
     * @param Tenant $tenant
     * @param bool $isGlobalKey Whether the request used global bridge key
     * @return bool
     */
    public function verifyPayloadSignature(Request $request, Tenant $tenant, bool $isGlobalKey): bool
    {
        return $this->verifier->verify($request, $tenant, $isGlobalKey);
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
        // Sanitize headers for logging (don't log secrets)
        $sanitizedHeaders = collect($request->headers->all())
            ->except(['x-api-key', 'authorization', 'x-payload-signature', 'x-timestamp', 'x-nonce'])
            ->all();
        
        \Log::info('WooCommerce order sync received', [
            'headers' => $sanitizedHeaders,
            'has_payload' => !empty($request->all())
        ]);

        // Get tenant from middleware (already validated)
        $tenant = $request->attributes->get('tenant');
        if (!$tenant) {
            // Fallback: find tenant if middleware didn't set it (shouldn't happen)
            $tenantId = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                \Log::error('Tenant not found in request attributes or by ID', ['tenant_id' => $tenantId]);
                return response()->json(['success' => false, 'message' => 'Invalid tenant'], 422);
            }
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

        // Fetch shipment ID consistently
        $shipmentId = Shipment::where('order_id', $order->id)->value('id');

        return response()->json([
            'success' => true,
            'message' => 'Order synced successfully',
            'data' => [
                'success' => true,
                'message' => 'Order synced successfully',
                'order_id' => $order->id,
                'shipment_id' => $shipmentId,
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

