# 🔑 API Keys Setup for AI Features

To enable the AI features, you need to configure the following API keys in your `.env` file:

## Required API Keys

### 1. **OpenWeather API** (for weather impact analysis)
```env
OPENWEATHER_API_KEY=your_openweather_api_key_here
```
- **Purpose**: Get real-time weather data for delivery delay predictions
- **Get API Key**: https://openweathermap.org/api
- **Free Tier**: 1,000 calls/day
- **Cost**: Free for basic usage

### 2. **Google Maps API** (for traffic analysis)
```env
GOOGLE_MAPS_API_KEY=your_google_maps_api_key_here
```
- **Purpose**: Get traffic data for route optimization
- **Get API Key**: https://developers.google.com/maps/documentation/javascript/get-api-key
- **Free Tier**: $200/month credit
- **Cost**: Pay-per-use

### 3. **OpenAI API** (for chatbot)
```env
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_ENDPOINT=https://api.openai.com/v1/chat/completions
```
- **Purpose**: Power the AI chatbot with GPT-3.5-turbo
- **Get API Key**: https://platform.openai.com/api-keys
- **Free Tier**: $5 credit for new accounts
- **Cost**: $0.002 per 1K tokens

### 4. **ML Model Endpoint** (optional, for advanced predictions)
```env
ML_MODEL_ENDPOINT=your_ml_model_endpoint_here
```
- **Purpose**: Custom ML model for advanced predictions
- **Optional**: System works without this
- **Cost**: Depends on your ML service

## Setup Instructions

### Step 1: Add to .env file
Add these lines to your `.env` file:

```env
# AI Features API Keys
OPENWEATHER_API_KEY=your_openweather_api_key_here
GOOGLE_MAPS_API_KEY=your_google_maps_api_key_here
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_ENDPOINT=https://api.openai.com/v1/chat/completions
ML_MODEL_ENDPOINT=your_ml_model_endpoint_here
```

### Step 2: Get API Keys

#### OpenWeather API
1. Go to https://openweathermap.org/api
2. Sign up for a free account
3. Go to "API Keys" section
4. Copy your API key
5. Add to `.env` file

#### Google Maps API
1. Go to https://console.cloud.google.com/
2. Create a new project or select existing
3. Enable "Maps JavaScript API" and "Directions API"
4. Go to "Credentials" → "Create Credentials" → "API Key"
5. Copy your API key
6. Add to `.env` file

#### OpenAI API
1. Go to https://platform.openai.com/
2. Sign up for an account
3. Go to "API Keys" section
4. Create a new API key
5. Copy your API key
6. Add to `.env` file

### Step 3: Test the Setup
```bash
# Test predictive ETAs
php artisan predictive-eta:update

# Test chatbot (in tinker)
php artisan tinker
>>> $chatbot = app(\App\Services\ChatbotService::class);
>>> $session = $chatbot->createSession('tenant-id', 'customer-id');
>>> $chatbot->processMessage($session, 'Hello');
```

## Fallback Behavior

The system is designed to work even without API keys:

- **No Weather API**: Uses mock weather data (low impact)
- **No Traffic API**: Uses time-based traffic logic
- **No OpenAI API**: Uses fallback responses
- **No ML Model**: Uses simple calculation logic

## Cost Estimation

### Monthly Costs (estimated):
- **OpenWeather**: Free (1,000 calls/day)
- **Google Maps**: $0-50 (depending on usage)
- **OpenAI**: $10-100 (depending on chatbot usage)
- **Total**: $10-150/month

### Usage Optimization:
- Weather API: Called once per shipment
- Traffic API: Called once per shipment
- OpenAI API: Called per chatbot message
- ML Model: Called once per prediction

## Troubleshooting

### Common Issues:

1. **"API key not configured" errors**
   - Check if API keys are in `.env` file
   - Restart your application after adding keys
   - Check for typos in API key names

2. **"API quota exceeded" errors**
   - Check your API usage limits
   - Consider upgrading your plan
   - Implement rate limiting

3. **"API connection failed" errors**
   - Check your internet connection
   - Verify API endpoints are correct
   - Check API key permissions

### Testing Commands:
```bash
# Test weather API
php artisan tinker
>>> $service = app(\App\Services\PredictiveEtaService::class);
>>> $shipment = \App\Models\Shipment::first();
>>> $service->getWeatherImpact($shipment);

# Test chatbot
>>> $chatbot = app(\App\Services\ChatbotService::class);
>>> $session = $chatbot->createSession('tenant-id');
>>> $chatbot->processMessage($session, 'Where is my package?');
```

## Security Notes

- **Never commit API keys to version control**
- **Use environment variables for all keys**
- **Rotate API keys regularly**
- **Monitor API usage for unusual activity**
- **Set up billing alerts for paid APIs**

## Support

If you need help with API setup:
1. Check the logs: `storage/logs/laravel.log`
2. Test individual services in tinker
3. Check API documentation for each service
4. Contact support if issues persist
