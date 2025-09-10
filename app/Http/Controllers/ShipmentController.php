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
        $shipments = QueryBuilder::for(
                Shipment::select([
                    'id','tracking_number','order_id','status',
                    'estimated_delivery','created_at','updated_at',
                    'customer_id','courier_id','shipping_address',
                    'billing_address','weight',
                    'shipping_cost','courier_tracking_id',
                ])
            )
            ->with([
                'customer:id,name,email,phone',
                'courier:id,name,code,tracking_url_template',
            ])
            ->allowedFilters([
                AllowedFilter::partial('tracking_number'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('courier', function ($query, $value) {
                    if (!filled($value)) return;
                    $query->whereHas('courier', function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                          ->orWhere('code', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::callback('customer', function ($query, $value) {
                    if (!filled($value)) return;
                    $query->whereHas('customer', function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                          ->orWhere('email', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'status', 'tracking_number', 'estimated_delivery'])
            ->defaultSort('-created_at')
            ->paginate(20)
            ->withQueryString()
            // keep paginator shape while transforming items:
            ->through(fn ($shipment) => new ShipmentResource($shipment));

        return Inertia::render('Shipments/Index', [
            'shipments' => $shipments,
            'filters'   => $request->only([
                'filter.tracking_number', 'filter.status', 'filter.courier', 'filter.customer',
            ]),
        ]);
    }

    public function show(Shipment $shipment): Response
    {
        $shipment->load(['customer', 'courier']);

        return Inertia::render('Shipments/Show', [
            'shipment' => new ShipmentResource($shipment),

            // Lazy heavy data:
            'statusHistory' => Inertia::lazy(function () use ($shipment) {
                return $shipment->statusHistory()
                    ->orderBy('happened_at', 'desc')
                    ->get()
                    ->map(fn ($s) => [
                        'status'      => $s->status,
                        'happened_at' => $s->happened_at?->format('Y-m-d H:i:s'),
                        'location'    => $s->location,
                        'notes'       => $s->notes,
                    ]);
            }),

            'notifications' => Inertia::lazy(function () use ($shipment) {
                return $shipment->notifications()
                    ->orderBy('sent_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(fn ($n) => [
                        'id'     => $n->id,
                        'type'   => $n->type,
                        'sent_at'=> $n->sent_at?->format('Y-m-d H:i:s'),
                        'status' => $n->status,
                    ]);
            }),
        ]);
    }
}
