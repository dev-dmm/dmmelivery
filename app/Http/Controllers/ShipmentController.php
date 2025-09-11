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
            ])
            ->allowedSorts(['created_at','status','tracking_number','estimated_delivery'])
            ->defaultSort('-created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn($s) => new ShipmentResource($s));
    
        return Inertia::render('Shipments/Index', [
            'shipments' => $shipments,
            'filters' => $request->only([
                'filter.tracking_number','filter.status','filter.courier','filter.customer',
            ]),
        ]);
    }

    public function show(Shipment $shipment): Response
    {
        $shipment->load(['customer', 'courier']);

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
}
