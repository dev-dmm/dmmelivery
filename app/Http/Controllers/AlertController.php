<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Services\AlertSystemService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    private AlertSystemService $alertSystemService;

    public function __construct(AlertSystemService $alertSystemService)
    {
        $this->alertSystemService = $alertSystemService;
    }

    /**
     * Display alerts dashboard
     */
    public function index(Request $request): Response
    {
        $tenant = Auth::user()->currentTenant();
        
        $query = Alert::where('tenant_id', $tenant->id)
            ->with(['alertRule', 'shipment.customer', 'shipment.courier']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('severity')) {
            $query->where('severity_level', $request->severity);
        }
        
        if ($request->has('type')) {
            $query->where('alert_type', $request->type);
        }

        $alerts = $query->latest('triggered_at')->paginate(20);

        $stats = [
            'total_alerts' => $alerts->total(),
            'active_alerts' => Alert::where('tenant_id', $tenant->id)->where('status', 'active')->count(),
            'acknowledged_alerts' => Alert::where('tenant_id', $tenant->id)->where('status', 'acknowledged')->count(),
            'resolved_alerts' => Alert::where('tenant_id', $tenant->id)->where('status', 'resolved')->count(),
            'critical_alerts' => Alert::where('tenant_id', $tenant->id)->where('severity_level', 'critical')->count(),
        ];

        return Inertia::render('Alerts/Index', [
            'alerts' => $alerts,
            'stats' => $stats,
            'filters' => $request->only(['status', 'severity', 'type']),
        ]);
    }

    /**
     * Display alert rules management
     */
    public function rules(): Response
    {
        $tenant = Auth::user()->currentTenant();
        
        $rules = AlertRule::where('tenant_id', $tenant->id)
            ->withCount('alerts')
            ->latest()
            ->paginate(20);

        $stats = [
            'total_rules' => AlertRule::where('tenant_id', $tenant->id)->count(),
            'active_rules' => AlertRule::where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'inactive_rules' => AlertRule::where('tenant_id', $tenant->id)->where('is_active', false)->count(),
            'alerts_triggered' => Alert::where('tenant_id', $tenant->id)->count(),
        ];

        return Inertia::render('Alerts/Rules', [
            'rules' => $rules,
            'stats' => $stats,
        ]);
    }

    /**
     * Create new alert rule
     */
    public function createRule(Request $request): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_conditions' => 'required|array',
            'alert_type' => 'required|string|in:delay,stuck,route_deviation,weather_impact,courier_performance,predictive_delay',
            'severity_level' => 'required|string|in:low,medium,high,critical',
            'notification_channels' => 'required|array',
            'is_active' => 'boolean',
        ]);

        $rule = AlertRule::create([
            'tenant_id' => $tenant->id,
            'name' => $request->name,
            'description' => $request->description,
            'trigger_conditions' => $request->trigger_conditions,
            'alert_type' => $request->alert_type,
            'severity_level' => $request->severity_level,
            'notification_channels' => $request->notification_channels,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alert rule created successfully',
            'data' => $rule,
        ]);
    }

    /**
     * Update alert rule
     */
    public function updateRule(Request $request, string $id): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $rule = AlertRule::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_conditions' => 'required|array',
            'alert_type' => 'required|string|in:delay,stuck,route_deviation,weather_impact,courier_performance,predictive_delay',
            'severity_level' => 'required|string|in:low,medium,high,critical',
            'notification_channels' => 'required|array',
            'is_active' => 'boolean',
        ]);

        $rule->update($request->only([
            'name', 'description', 'trigger_conditions', 'alert_type',
            'severity_level', 'notification_channels', 'is_active'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Alert rule updated successfully',
            'data' => $rule,
        ]);
    }

    /**
     * Delete alert rule
     */
    public function deleteRule(string $id): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $rule = AlertRule::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Alert rule deleted successfully',
        ]);
    }

    /**
     * Acknowledge alert
     */
    public function acknowledge(Request $request, string $id): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $alert = Alert::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $alert->acknowledge(Auth::user());

        return response()->json([
            'success' => true,
            'message' => 'Alert acknowledged successfully',
        ]);
    }

    /**
     * Resolve alert
     */
    public function resolve(Request $request, string $id): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $alert = Alert::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $request->validate([
            'resolution' => 'nullable|string|max:500',
        ]);

        $alert->resolve(Auth::user(), $request->resolution);

        return response()->json([
            'success' => true,
            'message' => 'Alert resolved successfully',
        ]);
    }

    /**
     * Escalate alert
     */
    public function escalate(string $id): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $alert = Alert::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $alert->escalate();

        return response()->json([
            'success' => true,
            'message' => 'Alert escalated successfully',
        ]);
    }

    /**
     * Run alert check manually
     */
    public function checkAlerts(): JsonResponse
    {
        try {
            $alertsTriggered = $this->alertSystemService->checkAllShipments();
            
            return response()->json([
                'success' => true,
                'message' => "Alert check completed. Triggered {$alertsTriggered} alerts",
                'alerts_triggered' => $alertsTriggered,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check alerts: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get alert details
     */
    public function show(string $id): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $alert = Alert::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->with(['alertRule', 'shipment.customer', 'shipment.courier', 'acknowledgedBy', 'resolvedBy'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $alert->id,
                'title' => $alert->title,
                'description' => $alert->description,
                'alert_type' => $alert->alert_type,
                'severity_level' => $alert->severity_level,
                'severity_color' => $alert->severity_color,
                'severity_icon' => $alert->severity_icon,
                'status' => $alert->status,
                'status_color' => $alert->status_color,
                'status_icon' => $alert->status_icon,
                'triggered_at' => $alert->triggered_at->format('Y-m-d H:i:s'),
                'time_since_triggered' => $alert->time_since_triggered,
                'escalation_level' => $alert->escalation_level,
                'escalation_status' => $alert->escalation_status,
                'acknowledged_at' => $alert->acknowledged_at?->format('Y-m-d H:i:s'),
                'acknowledged_by' => $alert->acknowledgedBy?->name,
                'resolved_at' => $alert->resolved_at?->format('Y-m-d H:i:s'),
                'resolved_by' => $alert->resolvedBy?->name,
                'shipment' => [
                    'id' => $alert->shipment->id,
                    'tracking_number' => $alert->shipment->tracking_number,
                    'status' => $alert->shipment->status,
                    'customer' => $alert->shipment->customer?->name,
                    'courier' => $alert->shipment->courier?->name,
                ],
                'rule' => [
                    'id' => $alert->alertRule->id,
                    'name' => $alert->alertRule->name,
                    'description' => $alert->alertRule->description,
                ],
                'metadata' => $alert->metadata,
            ],
        ]);
    }
}
