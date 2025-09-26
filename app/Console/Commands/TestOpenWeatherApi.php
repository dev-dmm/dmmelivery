<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestOpenWeatherApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:openweather {--city=Athens} {--country=GR}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test OpenWeather API key configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔑 Testing OpenWeather API Configuration...');
        $this->newLine();

        // Get API key from config
        $apiKey = config('services.openweather.key');
        
        if (!$apiKey) {
            $this->error('❌ OPENWEATHER_API_KEY not found in configuration');
            $this->line('Please add OPENWEATHER_API_KEY=your_key_here to your .env file');
            return 1;
        }

        $this->line("🔑 API Key: " . substr($apiKey, 0, 8) . "...");
        
        $city = $this->option('city');
        $country = $this->option('country');
        
        $this->line("🌍 Testing with location: {$city}, {$country}");
        $this->newLine();

        try {
            // First try the new One Call API 3.0 (requires separate subscription)
            $response = Http::timeout(10)->get('https://api.openweathermap.org/data/3.0/onecall', [
                'lat' => 37.9755, // Athens coordinates
                'lon' => 23.7348,
                'appid' => $apiKey,
                'units' => 'metric',
                'exclude' => 'minutely,daily,alerts'
            ]);

            // If One Call API fails, fall back to old API
            if (!$response->successful()) {
                $this->line("One Call API 3.0 not available, trying old API...");
                $response = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', [
                    'q' => $city . ',' . $country,
                    'appid' => $apiKey,
                    'units' => 'metric'
                ]);
            }

            if (!$response->successful()) {
                $this->error("❌ HTTP Error: " . $response->status());
                
                $data = $response->json();
                if (isset($data['message'])) {
                    $this->line("API Message: " . $data['message']);
                }

                if ($response->status() === 401) {
                    $this->newLine();
                    $this->line('🔍 TROUBLESHOOTING 401 Unauthorized:');
                    $this->line('1. Check if your API key is correct');
                    $this->line('2. Make sure your API key is activated (it may take a few minutes after creation)');
                    $this->line('3. Verify you\'re using the correct API key format (32 characters)');
                    $this->line('4. Check if you have any usage limits or billing issues');
                    $this->newLine();
                    $this->line('💡 Try visiting: https://openweathermap.org/api to verify your key');
                }
                
                return 1;
            }

            $weather = $response->json();
            
            $this->info('✅ SUCCESS: OpenWeather API key is working!');
            $this->newLine();
            
            // Handle both API versions
            if (isset($weather['current'])) {
                // One Call API 3.0 response
                $current = $weather['current'];
                $this->line("🌤️  Weather Data for Athens, Greece (One Call API 3.0):");
                $this->line("   Temperature: " . round($current['temp']) . "°C");
                $this->line("   Feels like: " . round($current['feels_like']) . "°C");
                $this->line("   Humidity: " . $current['humidity'] . "%");
                $this->line("   Conditions: " . $current['weather'][0]['main'] . " - " . $current['weather'][0]['description']);
                $this->line("   Wind: " . $current['wind_speed'] . " m/s");
                $this->line("   Visibility: " . ($current['visibility'] / 1000) . " km");
            } else {
                // Old API v2.5 response
                $this->line("🌤️  Weather Data for {$city}, {$country} (API v2.5):");
                $this->line("   Temperature: " . round($weather['main']['temp']) . "°C");
                $this->line("   Feels like: " . round($weather['main']['feels_like']) . "°C");
                $this->line("   Humidity: " . $weather['main']['humidity'] . "%");
                $this->line("   Conditions: " . $weather['weather'][0]['main'] . " - " . $weather['weather'][0]['description']);
                $this->line("   Wind: " . $weather['wind']['speed'] . " m/s");
                $this->line("   Visibility: " . ($weather['visibility'] / 1000) . " km");
            }
            
            $this->newLine();
            $this->info('🎉 Your OpenWeather API key is configured correctly!');
            $this->line('The system can now use weather data for delivery predictions.');
            
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error testing OpenWeather API: ' . $e->getMessage());
            Log::error('OpenWeather API test failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }
}
