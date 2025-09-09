<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Courier;

class WooCommerceOrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Read headers
        $headerKey = $request->header('X-Api-Key');
        $tenantId  = $request->header('X-Tenant-Id') ?? $request->input('tenant_id');

        if (!$headerKey) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Invalid tenant'], 422);
        }

        // Accept global bridge key OR tenant-specific token
        $globalKey = (string) config('services.dm_bridge.key');
        $isGlobalKeyValid = $globalKey && hash_equals($globalKey, (string) $headerKey);
        $isTenantTokenValid = $tenant->isApiTokenValid((string) $headerKey);

        if (!$isGlobalKeyValid && !$isTenantTokenValid) {
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
            return response()->json(['success'=>false, 'message'=>'Validation failed', 'errors'=>$v->errors()], 422);
        }

        // If order already exists, return it (idempotency)
        $externalId = data_get($request, 'order.external_order_id');
        $existing = Order::where('tenant_id', $tenant->id)
            ->where('external_order_id', $externalId)
            ->first();

        if ($existing) {
            return response()->json([
                'success'     => true,
                'message'     => 'Order already exists',
                'order_id'    => $existing->id,
                'shipment_id' => Shipment::where('order_id', $existing->id)->value('id'),
            ], 200);
        }

        // Upsert customer (simple)
        $customer = Customer::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email'     => data_get($request, 'customer.email') ?: Str::uuid().'@no-email.local',
            ],
            [
                'name'  => trim(implode(' ', array_filter([
                    data_get($request, 'customer.first_name'),
                    data_get($request, 'customer.last_name'),
                ]))),
                'phone' => data_get($request, 'customer.phone'),
            ]
        );

        // Create order
        $order = Order::create([
            'tenant_id'        => $tenant->id,
            'external_order_id'=> $externalId,
            'order_number'     => data_get($request, 'order.order_number'),
            'status'           => data_get($request, 'order.status', 'pending'),
            'total_amount'     => (float) data_get($request, 'order.total_amount', 0),
            'subtotal'         => (float) data_get($request, 'order.subtotal', 0),
            'tax_amount'       => (float) data_get($request, 'order.tax_amount', 0),
            'shipping_cost'    => (float) data_get($request, 'order.shipping_cost', 0),
            'discount_amount'  => (float) data_get($request, 'order.discount_amount', 0),
            'currency'         => data_get($request, 'order.currency', 'EUR'),
            'payment_status'   => data_get($request, 'order.payment_status', 'pending'),
            'payment_method'   => data_get($request, 'order.payment_method'),
            'customer_id'      => $customer->id,
            
            // Shipping address
            'shipping_address'     => data_get($request, 'shipping.address.address_1'),
            'shipping_city'        => data_get($request, 'shipping.address.city'),
            'shipping_postal_code' => data_get($request, 'shipping.address.postcode'),
            'shipping_country'     => data_get($request, 'shipping.address.country', 'GR'),
        ]);

        // Create shipment (default true)
        $shipment = null;
        if ($request->boolean('create_shipment', true)) {
            $courier = Courier::where('tenant_id', $tenant->id)
                ->where('is_default', true)
                ->first() ?? Courier::where('tenant_id', $tenant->id)->first();

            if (!$courier) {
                return response()->json(['success'=>false,'message'=>'No courier configured for tenant'], 422);
            }

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
                'id'                  => Str::uuid(),
                'tenant_id'           => $tenant->id,
                'order_id'            => $order->id,
                'customer_id'         => $customer->id,
                'courier_id'          => $courier->id,
                'tracking_number'     => $tracking,
                'courier_tracking_id' => '',
                'status'              => $request->input('desired_shipment_status', 'pending'),
                'weight'              => data_get($request, 'shipping.weight'),
                'dimensions'          => null,
                'shipping_address'    => $this->formatAddress($addr),
                'billing_address'     => null,
                'shipping_cost'       => (float) data_get($request, 'order.shipping_cost', 0),
                'estimated_delivery'  => null,
                'actual_delivery'     => null,
                'courier_response'    => null,
            ]);
        }

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
}
