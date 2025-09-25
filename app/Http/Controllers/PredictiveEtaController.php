<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\PredictiveEta;
use App\Services\PredictiveEtaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PredictiveEtaController extends Controller
{
    private PredictiveEtaService $predictiveEtaService;

    public function __construct(PredictiveEtaService $predictiveEtaService)
    {
        $this->predictiveEtaService = $predictiveEtaService;
    }

    /**
     * Display predictive ETAs dashboard
     */
    public function index(): Response
    {
        $tenant = Auth::user()->currentTenant();
        
        $predictiveEtas = PredictiveEta::where('tenant_id', $tenant->id)
            ->with(['shipment.customer', 'shipment.courier'])
            ->latest('last_updated_at')
            ->paginate(20);

        $stats = [
            'total_predictions' => $predictiveEtas->total(),
            'high_risk_predictions' => PredictiveEta::where('tenant_id', $tenant->id)
                ->whereIn('delay_risk_level', ['high', 'critical'])
                ->count(),
            'low_confidence_predictions' => PredictiveEta::where('tenant_id', $tenant->id)
                ->where('confidence_score', '<', 0.5)
                ->count(),
            'avg_confidence' => PredictiveEta::where('tenant_id', $tenant->id)
                ->avg('confidence_score'),
        ];

        return Inertia::render('PredictiveEta/Index', [
            'predictiveEtas' => $predictiveEtas,
            'stats' => $stats,
        ]);
    }

    /**
     * Generate predictive ETA for a shipment
     */
    public function generate(Request $request, string $shipmentId): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $shipment = Shipment::where('tenant_id', $tenant->id)
            ->where('id', $shipmentId)
            ->firstOrFail();

        try {
            $predictiveEta = $this->predictiveEtaService->generatePredictiveEta($shipment);
            
            return response()->json([
                'success' => true,
                'message' => 'Predictive ETA generated successfully',
                'data' => [
                    'id' => $predictiveEta->id,
                    'original_eta' => $predictiveEta->original_eta?->format('Y-m-d H:i:s'),
                    'predicted_eta' => $predictiveEta->predicted_eta?->format('Y-m-d H:i:s'),
                    'confidence_score' => $predictiveEta->confidence_score,
                    'delay_risk_level' => $predictiveEta->delay_risk_level,
                    'delay_factors' => $predictiveEta->delay_factors,
                    'weather_impact' => $predictiveEta->weather_impact,
                    'traffic_impact' => $predictiveEta->traffic_impact,
                    'route_suggestions' => $predictiveEta->route_optimization_suggestions,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate predictive ETA: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update all predictive ETAs
     */
    public function updateAll(): JsonResponse
    {
        try {
            $updated = $this->predictiveEtaService->updateAllPredictiveEtas();
            
            return response()->json([
                'success' => true,
                'message' => "Updated predictive ETAs for {$updated} shipments",
                'updated_count' => $updated,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update predictive ETAs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get predictive ETA details
     */
    public function show(string $id)
    {
        $tenant = Auth::user()->currentTenant();
        
        $predictiveEta = PredictiveEta::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->with(['shipment.customer', 'shipment.courier', 'shipment.statusHistory'])
            ->firstOrFail();

        return inertia('PredictiveEta/Show', [
            'predictiveEta' => [
                'id' => $predictiveEta->id,
                'shipment' => [
                    'id' => $predictiveEta->shipment->id,
                    'tracking_number' => $predictiveEta->shipment->tracking_number,
                    'status' => $predictiveEta->shipment->status,
                    'customer' => $predictiveEta->shipment->customer?->name,
                    'courier' => $predictiveEta->shipment->courier?->name,
                ],
                'original_eta' => $predictiveEta->original_eta?->format('Y-m-d H:i:s'),
                'predicted_eta' => $predictiveEta->predicted_eta?->format('Y-m-d H:i:s'),
                'confidence_score' => $predictiveEta->confidence_score,
                'delay_risk_level' => $predictiveEta->delay_risk_level,
                'delay_risk_color' => $predictiveEta->delay_risk_color,
                'delay_risk_icon' => $predictiveEta->delay_risk_icon,
                'delay_factors' => $predictiveEta->delay_factors,
                'weather_impact' => $predictiveEta->weather_impact,
                'traffic_impact' => $predictiveEta->traffic_impact,
                'historical_accuracy' => $predictiveEta->historical_accuracy,
                'route_optimization_suggestions' => $predictiveEta->route_optimization_suggestions,
                'delay_explanation' => $predictiveEta->getDelayExplanation(),
                'has_significant_delay' => $predictiveEta->hasSignificantDelay(),
                'last_updated_at' => $predictiveEta->last_updated_at?->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
