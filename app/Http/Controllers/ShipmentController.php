<?php
// app/Http/Controllers/ShipmentController.php

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
        $shipments = QueryBuilder::for(Shipment::class)
            ->with(['customer', 'courier'])
            ->allowedFilters([
                AllowedFilter::partial('tracking_number'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('courier', function ($query, $value) {
                    $query->whereHas('courier', function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                          ->orWhere('code', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::callback('customer', function ($query, $value) {
                    $query->whereHas('customer', function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                          ->orWhere('email', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'status', 'tracking_number', 'estimated_delivery'])
            ->defaultSort('-created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Shipments/Index', [
            'shipments' => ShipmentResource::collection($shipments),
            'filters' => $request->only([
                'filter.tracking_number', 
                'filter.status', 
                'filter.courier', 
                'filter.customer'
            ]),
        ]);
    }

    public function show(Shipment $shipment): Response
    {
        // Load basic shipment data
        $shipment->load(['customer', 'courier']);

        return Inertia::render('Shipments/Show', [
            'shipment' => new ShipmentResource($shipment),
            // Lazy load heavy status history and notifications
            'statusHistory' => Inertia::lazy(function () use ($shipment) {
                return $shipment->statusHistory()
                    ->orderBy('happened_at', 'desc')
                    ->get()
                    ->map(function ($status) {
                        return [
                            'status' => $status->status,
                            'happened_at' => $status->happened_at?->format('Y-m-d H:i:s'),
                            'location' => $status->location,
                            'notes' => $status->notes,
                        ];
                    });
            }),
            'notifications' => Inertia::lazy(function () use ($shipment) {
                return $shipment->notifications()
                    ->orderBy('sent_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($notification) {
                        return [
                            'id' => $notification->id,
                            'type' => $notification->type,
                            'sent_at' => $notification->sent_at?->format('Y-m-d H:i:s'),
                            'status' => $notification->status,
                        ];
                    });
            }),
        ]);
    }
}