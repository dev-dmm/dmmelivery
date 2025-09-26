<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestAllApiKeys extends Command
{
    protected $signature = 'test:api-keys';
    protected $description = 'Test all API keys configuration';

    public function handle()
    {
        $this->info('🔑 Testing All API Keys Configuration...');
        $this->newLine();

        // Test OpenWeather API
        $this->testOpenWeatherApi();
        $this->newLine();

        // Test Google Maps API
        $this->testGoogleMapsApi();
        $this->newLine();

        // Test OpenAI API
        $this->testOpenAiApi();
        $this->newLine();

        // Test ML Model Endpoint
        $this->testMlModelEndpoint();
    }

    private function testOpenWeatherApi()
    {
        $this->line('🌤️  Testing OpenWeather API...');
        
        $apiKey = config('services.openweather.key');
        
        if (!$apiKey || $apiKey === 'your_openweather_api_key_here') {
            $this->warn('   ❌ OpenWeather API key not configured');
            return;
        }

        try {
            $response = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', [
                'q' => 'Athens,GR',
                'appid' => $apiKey,
                'units' => 'metric'
            ]);

            if ($response->successful()) {
                $this->info('   ✅ OpenWeather API key is working!');
            } else {
                $this->error('   ❌ OpenWeather API key failed: ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->error('   ❌ OpenWeather API error: ' . $e->getMessage());
        }
    }

    private function testGoogleMapsApi()
    {
        $this->line('🗺️  Testing Google Maps API...');
        
        $apiKey = config('services.google_maps.key');
        
        if (!$apiKey || $apiKey === 'your_google_maps_api_key_here') {
            $this->warn('   ❌ Google Maps API key not configured');
            return;
        }

        try {
            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => 'Athens, Greece',
                'key' => $apiKey
            ]);

            if ($response->successful()) {
                $this->info('   ✅ Google Maps API key is working!');
            } else {
                $this->error('   ❌ Google Maps API key failed: ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Google Maps API error: ' . $e->getMessage());
        }
    }

    private function testOpenAiApi()
    {
        $this->line('🤖 Testing OpenAI API...');
        
        $apiKey = config('services.openai.key');
        
        if (!$apiKey || $apiKey === 'your_openai_api_key_here') {
            $this->warn('   ❌ OpenAI API key not configured');
            return;
        }

        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello']
                ],
                'max_tokens' => 10
            ]);

            if ($response->successful()) {
                $this->info('   ✅ OpenAI API key is working!');
            } else {
                $this->error('   ❌ OpenAI API key failed: ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->error('   ❌ OpenAI API error: ' . $e->getMessage());
        }
    }

    private function testMlModelEndpoint()
    {
        $this->line('🧠 Testing ML Model Endpoint...');
        
        $endpoint = config('services.ml_model.endpoint');
        
        if (!$endpoint || $endpoint === 'your_ml_model_endpoint_here') {
            $this->warn('   ❌ ML Model Endpoint not configured (optional)');
            return;
        }

        $this->info('   ✅ ML Model Endpoint configured: ' . $endpoint);
    }
}
