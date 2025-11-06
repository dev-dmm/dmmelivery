<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Debug Routes
|--------------------------------------------------------------------------
|
| Debug routes for development (should be removed or protected in production)
|
*/

Route::middleware(['auth', 'verified', 'identify.tenant'])->group(function () {
    // Simple debug route to check basic order data (tenant scoped only)
    Route::get('/debug-orders', function (Request $request) {
        $user     = $request->user();
        $tenantId = app('tenant')->id ?? $user->tenant_id ?? null;

        // Base query - tenant scoped only (no user filtering)
        $ordersQ = \App\Models\Order::query()
            ->where('tenant_id', $tenantId);

        $orders = (clone $ordersQ)->take(5)->get([
            // include only columns that exist
            'id',
            ...(Schema::hasColumn('orders', 'shipping_city') ? ['shipping_city'] : []),
            'shipping_address',
            'created_at',
        ]);

        $totalOrders = (clone $ordersQ)->count();

        return response()->json([
            'tenant_id'     => $tenantId,
            'user_id'       => $user->id,
            'scope'         => 'tenant',  // explicitly tenant-only
            'total_orders'  => $totalOrders,
            'sample_orders' => $orders,
        ]);
    })->name('debug.orders');

    // Debug route to check shipments data (tenant + user scoped)
    Route::get('/debug-shipments', function (Request $request) {
        $tenant   = app('tenant') ?? $request->user()?->tenant;
        abort_unless($tenant, 403, 'No tenant in context.');
    
        $tenantId = $tenant->id;
    
        $shipQ = \App\Models\Shipment::query()
            ->where('tenant_id', $tenantId);
    
        $shipments = (clone $shipQ)->take(5)->get([
            'id',
            'shipping_address',
            'status',
            'created_at',
        ]);
    
        return response()->json([
            'tenant_id'        => $tenantId,
            'scope'            => 'tenant',  // explicitly tenant-only
            'total_shipments'  => (clone $shipQ)->count(),
            'sample_shipments' => $shipments,
        ]);
    })->name('debug.shipments');

    // Debug route to check areas data (derived from ORDERS, tenant scoped only)
    Route::get('/debug-areas', function (Request $request) {
        $user     = $request->user();
        $tenantId = app('tenant')->id ?? $user->tenant_id ?? null;

        // Base orders query - tenant scoped only (no user filtering)
        $ordersQ = \App\Models\Order::query()
            ->where('tenant_id', $tenantId);

        // Areas from city column (only if it exists)
        $areasFromCityField = [];
        if (Schema::hasColumn('orders', 'shipping_city')) {
            $areasFromCityField = (clone $ordersQ)
                ->whereNotNull('shipping_city')
                ->where('shipping_city', '!=', '')
                ->distinct()
                ->pluck('shipping_city')
                ->toArray();
        }

        // Areas from address parsing
        $areasFromAddress = (clone $ordersQ)
            ->whereNotNull('shipping_address')
            ->where('shipping_address', '!=', '')
            ->get(['shipping_address'])
            ->map(function ($order) {
                $address = (string) $order->shipping_address;
                $parts = array_values(array_filter(
                    array_map('trim', explode(',', $address)),
                    fn ($p) => $p !== ''
                ));

                if (count($parts) >= 2) {
                    $countries = [
                        'greece','gr','united states','usa','us',
                        'uk','united kingdom','italy','de','germany',
                        'france','es','spain'
                    ];
                    $lastLower = mb_strtolower(end($parts));

                    if (in_array($lastLower, $countries, true) && count($parts) >= 2) {
                        $city = $parts[count($parts) - 2];
                    } else {
                        $city = end($parts);
                        if (preg_match('/^\d{3,6}(-\d{2,6})?$/', $city)) {
                            $city = (count($parts) >= 2) ? $parts[count($parts) - 2] : $city;
                        }
                    }

                    $city = trim($city);
                    if ($city !== '' && !in_array(mb_strtolower($city), $countries, true)) {
                        return $city;
                    }
                }
                return null;
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Sample orders (only columns that exist)
        $sampleCols = ['shipping_address'];
        if (Schema::hasColumn('orders', 'shipping_city')) {
            $sampleCols[] = 'shipping_city';
        }

        $sampleOrders = (clone $ordersQ)->take(5)->get($sampleCols);

        return response()->json([
            'tenant_id'             => $tenantId,
            'user_id'               => $user->id,
            'scope'                 => 'tenant',  // explicitly tenant-only
            'total_orders'          => (clone $ordersQ)->count(),
            'orders_with_city'      => Schema::hasColumn('orders', 'shipping_city')
                ? (clone $ordersQ)->whereNotNull('shipping_city')->count()
                : 0,
            'areas_from_city_field' => $areasFromCityField,
            'areas_from_address'    => $areasFromAddress,
            'sample_orders'         => $sampleOrders,
        ]);
    })->name('debug.areas');
});

