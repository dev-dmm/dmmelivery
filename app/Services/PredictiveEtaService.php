<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\PredictiveEta;
use App\Models\ShipmentStatusHistory;
use App\Services\Contracts\PredictiveEtaServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PredictiveEtaService implements PredictiveEtaServiceInterface
{
    private ?string $weatherApiKey;
    private ?string $trafficApiKey;
    private ?string $mlModelEndpoint;

    public function __construct()
    {
        $this->weatherApiKey = config('services.openweather.key');
        $this->trafficApiKey = config('services.google_maps.key');
        $this->mlModelEndpoint = config('services.ml_model.endpoint');
    }

    /**
     * Generate predictive ETA for a shipment
     */
    public function generatePredictiveEta(Shipment $shipment): PredictiveEta
    {
        Log::info("🤖 Generating predictive ETA for shipment: {$shipment->tracking_number}");

        // Get historical data for similar shipments
        $historicalData = $this->getHistoricalShipmentData($shipment);
        
        // Get external factors
        $weatherData = $this->getWeatherImpact($shipment);
        $trafficData = $this->getTrafficImpact($shipment);
        
        // Get current shipment progress
        $progressData = $this->getShipmentProgress($shipment);
        
        // Calculate ML prediction
        $prediction = $this->calculateMLPrediction([
            'shipment' => $shipment,
            'historical' => $historicalData,
            'weather' => $weatherData,
            'traffic' => $trafficData,
            'progress' => $progressData,
        ]);

        // Create or update predictive ETA
        $predictiveEta = PredictiveEta::updateOrCreate(
            ['shipment_id' => $shipment->id],
            [
                'tenant_id' => $shipment->tenant_id,
                'original_eta' => $shipment->estimated_delivery,
                'predicted_eta' => $prediction['predicted_eta'],
                'confidence_score' => $prediction['confidence_score'],
                'delay_factors' => $prediction['delay_factors'],
                'weather_impact' => $weatherData['impact_score'],
                'traffic_impact' => $trafficData['impact_score'],
                'historical_accuracy' => $prediction['historical_accuracy'],
                'route_optimization_suggestions' => $prediction['route_suggestions'],
                'last_updated_at' => now(),
            ]
        );

        Log::info("✅ Predictive ETA generated: {$predictiveEta->predicted_eta} (confidence: {$predictiveEta->confidence_score})");

        return $predictiveEta;
    }

    /**
     * Get historical data for similar shipments
     */
    private function getHistoricalShipmentData(Shipment $shipment): array
    {
        $courierId = $shipment->courier_id;
        $shippingCity = $this->extractCityFromAddress($shipment->shipping_address);
        $weight = $shipment->weight;

        // Get similar shipments from last 6 months
        $similarShipments = Shipment::where('tenant_id', $shipment->tenant_id)
            ->where('courier_id', $courierId)
            ->where('created_at', '>=', now()->subMonths(6))
            ->where('status', 'delivered')
            ->whereNotNull('actual_delivery')
            ->whereNotNull('estimated_delivery')
            ->get();

        $delays = [];
        $onTimeDeliveries = 0;
        $totalDeliveries = 0;

        foreach ($similarShipments as $similar) {
            $estimated = $similar->estimated_delivery;
            $actual = $similar->actual_delivery;
            
            if ($estimated && $actual) {
                $delayHours = $actual->diffInHours($estimated, false);
                $delays[] = $delayHours;
                $totalDeliveries++;
                
                if ($delayHours <= 2) {
                    $onTimeDeliveries++;
                }
            }
        }

        $avgDelay = count($delays) > 0 ? array_sum($delays) / count($delays) : 0;
        $onTimeRate = $totalDeliveries > 0 ? $onTimeDeliveries / $totalDeliveries : 0.8;

        return [
            'avg_delay_hours' => $avgDelay,
            'on_time_rate' => $onTimeRate,
            'total_similar_shipments' => $totalDeliveries,
            'delay_distribution' => $delays,
        ];
    }

    /**
     * Get weather impact on delivery
     */
    private function getWeatherImpact(Shipment $shipment): array
    {
        if (!$this->weatherApiKey) {
            Log::info("Weather API key not configured, using mock data");
            return [
                'impact_score' => 0.1, // Low impact when no weather data
                'conditions' => 'unknown',
                'description' => 'Weather data unavailable',
                'wind_speed' => 0,
                'visibility' => 10000,
            ];
        }

        try {
            // Get coordinates for the shipping address
            $coordinates = $this->getCoordinatesFromAddress($shipment->shipping_address);
            
            if (!$coordinates) {
                Log::warning("Could not get coordinates for address: " . $shipment->shipping_address);
                return ['impact_score' => 0, 'conditions' => 'unknown'];
            }

            // Use One Call API 3.0 for better weather data
            $response = Http::timeout(10)->get('https://api.openweathermap.org/data/3.0/onecall', [
                'lat' => $coordinates['lat'],
                'lon' => $coordinates['lon'],
                'appid' => $this->weatherApiKey,
                'units' => 'metric',
                'exclude' => 'minutely,daily,alerts' // Only get current and hourly data
            ]);

            if (!$response->successful()) {
                Log::warning("Weather API request failed: " . $response->status());
                return ['impact_score' => 0, 'conditions' => 'unknown'];
            }

            $weather = $response->json();
            $current = $weather['current'] ?? [];
            
            $conditions = $current['weather'][0]['main'] ?? 'Clear';
            $description = $current['weather'][0]['description'] ?? '';
            $windSpeed = $current['wind_speed'] ?? 0;
            $visibility = $current['visibility'] ?? 10000;

            // Calculate impact score (0-1, where 1 is maximum delay)
            $impactScore = 0;
            
            if (in_array($conditions, ['Rain', 'Snow', 'Thunderstorm'])) {
                $impactScore += 0.4;
            }
            
            if ($windSpeed > 10) {
                $impactScore += 0.2;
            }
            
            if ($visibility < 1000) {
                $impactScore += 0.3;
            }

            return [
                'impact_score' => min($impactScore, 1.0),
                'conditions' => $conditions,
                'description' => $description,
                'wind_speed' => $windSpeed,
                'visibility' => $visibility,
            ];

        } catch (\Exception $e) {
            Log::warning("Weather API error: " . $e->getMessage());
            return ['impact_score' => 0, 'conditions' => 'unknown'];
        }
    }

    /**
     * Get traffic impact on delivery
     */
    private function getTrafficImpact(Shipment $shipment): array
    {
        if (!$this->trafficApiKey) {
            Log::info("Traffic API key not configured, using time-based mock data");
            // Use time-based logic when no API key
            $currentHour = now()->hour;
            $isPeakHour = ($currentHour >= 7 && $currentHour <= 9) || ($currentHour >= 17 && $currentHour <= 19);
            $impactScore = $isPeakHour ? 0.3 : 0.1;
            
            return [
                'impact_score' => $impactScore,
                'is_peak_hour' => $isPeakHour,
                'traffic_level' => $isPeakHour ? 'high' : 'normal',
            ];
        }

        try {
            // This would integrate with Google Maps Traffic API
            // For now, return mock data based on time of day
            $currentHour = now()->hour;
            
            // Peak traffic hours (7-9 AM, 5-7 PM)
            $isPeakHour = ($currentHour >= 7 && $currentHour <= 9) || ($currentHour >= 17 && $currentHour <= 19);
            
            $impactScore = $isPeakHour ? 0.3 : 0.1;
            
            return [
                'impact_score' => $impactScore,
                'is_peak_hour' => $isPeakHour,
                'traffic_level' => $isPeakHour ? 'high' : 'normal',
            ];

        } catch (\Exception $e) {
            Log::warning("Traffic API error: " . $e->getMessage());
            return ['impact_score' => 0, 'is_peak_hour' => false];
        }
    }

    /**
     * Get current shipment progress
     */
    private function getShipmentProgress(Shipment $shipment): array
    {
        $statusHistory = $shipment->statusHistory()->orderBy('happened_at', 'desc')->get();
        $currentStatus = $statusHistory->first();
        
        $progressPercentage = match($shipment->status) {
            'pending' => 0,
            'picked_up' => 25,
            'in_transit' => 50,
            'out_for_delivery' => 75,
            'delivered' => 100,
            default => 0
        };

        $timeInCurrentStatus = $currentStatus ? 
            now()->diffInHours($currentStatus->happened_at) : 0;

        return [
            'current_status' => $shipment->status,
            'progress_percentage' => $progressPercentage,
            'time_in_current_status_hours' => $timeInCurrentStatus,
            'status_history_count' => $statusHistory->count(),
        ];
    }

    /**
     * Calculate ML prediction
     */
    private function calculateMLPrediction(array $data): array
    {
        $shipment = $data['shipment'];
        $historical = $data['historical'];
        $weather = $data['weather'];
        $traffic = $data['traffic'];
        $progress = $data['progress'];

        // Simple ML-like calculation (in production, this would call a real ML model)
        $baseEta = $shipment->estimated_delivery;
        if (!$baseEta) {
            $baseEta = now()->addDays(2); // Default 2 days
        }

        // Calculate delay factors
        $delayFactors = [
            'weather' => $weather['impact_score'],
            'traffic' => $traffic['impact_score'],
            'courier_performance' => 1 - $historical['on_time_rate'],
            'route_issues' => $progress['time_in_current_status_hours'] > 24 ? 0.3 : 0,
        ];

        $totalDelayImpact = array_sum($delayFactors);
        $delayHours = $totalDelayImpact * 24; // Convert to hours

        $predictedEta = $baseEta->copy()->addHours($delayHours);
        
        // Calculate confidence score
        $confidenceScore = max(0.1, 1 - ($totalDelayImpact * 0.5));
        
        // Generate route suggestions
        $routeSuggestions = [];
        if ($delayFactors['traffic'] > 0.2) {
            $routeSuggestions[] = "Consider alternative route to avoid traffic";
        }
        if ($delayFactors['weather'] > 0.3) {
            $routeSuggestions[] = "Weather conditions may cause delays";
        }

        return [
            'predicted_eta' => $predictedEta,
            'confidence_score' => $confidenceScore,
            'delay_factors' => $delayFactors,
            'historical_accuracy' => $historical['on_time_rate'],
            'route_suggestions' => $routeSuggestions,
        ];
    }

    /**
     * Extract city from shipping address
     */
    private function extractCityFromAddress(string $address): string
    {
        // Simple city extraction (in production, use proper address parsing)
        $parts = explode(',', $address);
        return trim($parts[count($parts) - 2] ?? 'Athens');
    }

    /**
     * Get coordinates from shipping address using geocoding
     */
    private function getCoordinatesFromAddress(string $address): ?array
    {
        try {
            // Use OpenWeather Geocoding API to get coordinates
            $response = Http::timeout(10)->get('http://api.openweathermap.org/geo/1.0/direct', [
                'q' => $address,
                'limit' => 1,
                'appid' => $this->weatherApiKey
            ]);

            if (!$response->successful()) {
                Log::warning("Geocoding API request failed: " . $response->status());
                return null;
            }

            $data = $response->json();
            if (empty($data)) {
                Log::warning("No coordinates found for address: " . $address);
                return null;
            }

            return [
                'lat' => $data[0]['lat'],
                'lon' => $data[0]['lon']
            ];

        } catch (\Exception $e) {
            Log::warning("Geocoding error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update all active shipments with predictive ETAs
     */
    public function updateAllPredictiveEtas(): int
    {
        $activeShipments = Shipment::whereNotIn('status', ['delivered', 'cancelled', 'returned'])
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $updated = 0;
        foreach ($activeShipments as $shipment) {
            try {
                $this->generatePredictiveEta($shipment);
                $updated++;
            } catch (\Exception $e) {
                Log::error("Failed to update predictive ETA for shipment {$shipment->tracking_number}: " . $e->getMessage());
            }
        }

        Log::info("Updated predictive ETAs for {$updated} shipments");
        return $updated;
    }
}
