<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Http\Resources\ShipmentResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class ShipmentController extends Controller
{
    public function index(Request $request): Response
    {
        // Get the authenticated user and their tenant
        $user = $request->user();
        
        if (!$user || !$user->tenant) {
            return redirect()->route('login')
                ->with('error', 'Unable to identify your tenant. Please log in again.');
        }

        $tenantId = $user->tenant->id;
        
        // Bind tenant to container for this request if not already bound
        if (!app()->bound('tenant')) {
            app()->instance('tenant', $user->tenant);
        }
    
        $base = \App\Models\Shipment::select([
            'id','tracking_number','order_id','status',
            'estimated_delivery','created_at','updated_at',
            'customer_id','courier_id','shipping_address',
            'billing_address','weight','shipping_cost','courier_tracking_id',
        ])->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));
    
        $shipments = QueryBuilder::for($base)
            ->with([
                'customer:id,name,email,phone',
                'courier:id,name,code,tracking_url_template',
                'predictiveEta:id,shipment_id,predicted_eta,confidence_score,delay_risk_level,delay_factors,weather_impact,traffic_impact',
            ])
            ->allowedFilters([
                AllowedFilter::partial('tracking_number'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('courier', function ($query, $value) {
                    if (!filled($value)) return;
                    $query->whereHas('courier', fn($q) =>
                        $q->where('name','like',"%{$value}%")
                          ->orWhere('code','like',"%{$value}%"));
                }),
                AllowedFilter::callback('customer', function ($query, $value) {
                    if (!filled($value)) return;
                    $query->whereHas('customer', fn($q) =>
                        $q->where('name','like',"%{$value}%")
                          ->orWhere('email','like',"%{$value}%"));
                }),
                AllowedFilter::callback('internal_id', function ($query, $value) {
                    if (!filled($value)) return;
                    $query->where('id', 'like', "%{$value}%")
                          ->orWhere('courier_tracking_id', 'like', "%{$value}%")
                          ->orWhereHas('order', fn($q) => 
                              $q->where('external_order_id', 'like', "%{$value}%")
                                ->orWhere('order_number', 'like', "%{$value}%"));
                }),
            ])
            ->allowedSorts(['created_at','status','tracking_number','estimated_delivery'])
            ->defaultSort('-created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn($s) => new ShipmentResource($s));
    
        return Inertia::render('Shipments/Index', [
            'shipments' => $shipments,
            'filters' => $request->only([
                'filter.tracking_number','filter.status','filter.courier','filter.customer','filter.internal_id',
            ]),
        ]);
    }

    public function show(Shipment $shipment): Response
    {
        $shipment->load(['customer', 'courier']);

        // Try to sync with real DMM Delivery data if available
        try {
            $dmmService = app(\App\Services\DMMDeliveryService::class);
            $dmmService->updateShipmentWithRealData($shipment);
            
            // Refresh the shipment to get updated data
            $shipment->refresh();
        } catch (\Exception $e) {
            // Log error but don't break the page
            \Log::warning('Failed to sync shipment with DMM Delivery data', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage()
            ]);
        }

        return Inertia::render('Shipments/Show', [
            'shipment' => new ShipmentResource($shipment),
            'statusHistory' => $shipment->statusHistory()
                ->orderBy('happened_at', 'desc')
                ->get()
                ->map(fn ($s) => [
                    'status'      => $s->status,
                    'happened_at' => $s->happened_at?->format('Y-m-d H:i:s'),
                    'location'    => $s->location,
                    'notes'       => $s->notes,
                ]),
            'notifications' => $shipment->notifications()
                ->orderBy('sent_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn ($n) => [
                    'id'     => $n->id,
                    'type'   => $n->type,
                    'sent_at'=> $n->sent_at?->format('Y-m-d H:i:s'),
                    'status' => $n->status,
                ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        
        if (!$user || !$user->tenant) {
            return redirect()->route('login')
                ->with('error', 'Unable to identify your tenant. Please log in again.');
        }

        $tenantId = $user->tenant->id;
        
        // Bind tenant to container for this request if not already bound
        if (!app()->bound('tenant')) {
            app()->instance('tenant', $user->tenant);
        }

        // Get couriers for the dropdown
        $couriers = \App\Models\Courier::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        // Get customers for the dropdown
        $customers = \App\Models\Customer::where('tenant_id', $tenantId)
            ->select('id', 'name', 'email', 'phone')
            ->orderBy('name')
            ->get();

        return Inertia::render('Shipments/Create', [
            'couriers' => $couriers,
            'customers' => $customers,
        ]);
    }

    public function search(Request $request): Response
    {
        $user = $request->user();
        
        if (!$user || !$user->tenant) {
            return redirect()->route('login')
                ->with('error', 'Unable to identify your tenant. Please log in again.');
        }

        $tenantId = $user->tenant->id;
        
        // Bind tenant to container for this request if not already bound
        if (!app()->bound('tenant')) {
            app()->instance('tenant', $user->tenant);
        }

        $searchQuery = $request->get('q', '');
        $searchResults = [];

        if (!empty($searchQuery)) {
            $searchResults = \App\Models\Shipment::where('tenant_id', $tenantId)
                ->where(function($query) use ($searchQuery) {
                    $query->where('tracking_number', 'like', "%{$searchQuery}%")
                          ->orWhere('id', 'like', "%{$searchQuery}%")
                          ->orWhere('courier_tracking_id', 'like', "%{$searchQuery}%")
                          ->orWhereHas('order', function($q) use ($searchQuery) {
                              $q->where('external_order_id', 'like', "%{$searchQuery}%")
                                ->orWhere('order_number', 'like', "%{$searchQuery}%");
                          })
                          ->orWhereHas('customer', function($q) use ($searchQuery) {
                              $q->where('name', 'like', "%{$searchQuery}%")
                                ->orWhere('email', 'like', "%{$searchQuery}%");
                          });
                })
                ->with(['customer:id,name,email', 'courier:id,name,code'])
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(fn($s) => new ShipmentResource($s));
        }

        return Inertia::render('Shipments/Search', [
            'searchQuery' => $searchQuery,
            'searchResults' => $searchResults,
        ]);
    }
}
