<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use ZipArchive;

class SettingsController extends Controller
{
    /**
     * Display the settings page
     */
    public function index(): Response
    {
        $tenant = Auth::user()->currentTenant();
        
        return Inertia::render('Settings/Index', [
            'tenant' => [
                'id' => $tenant->id,
                'business_name' => $tenant->business_name,
                'business_type' => $tenant->business_type,
                'contact_email' => $tenant->contact_email,
                'contact_phone' => $tenant->contact_phone,
                'business_address' => $tenant->business_address,
                'website_url' => $tenant->website_url,
                
                // Courier API Settings (now managed through WordPress plugin)
                'courier_integration_note' => 'Courier API credentials are now managed through the WordPress plugin',
                
                // Business Settings
                'default_currency' => $tenant->default_currency ?? 'EUR',
                'tax_rate' => $tenant->tax_rate ?? 24.0,
                'shipping_cost' => $tenant->shipping_cost ?? 0.0,
                'auto_create_shipments' => $tenant->auto_create_shipments ?? false,
                'send_notifications' => $tenant->send_notifications ?? true,
                
                // API & Integration Settings
                'api_token' => $tenant->api_token ? 'configured' : null,
                'webhook_url' => $tenant->webhook_url,
                'webhook_secret' => $tenant->webhook_secret ? '••••••••' : null,
                
                // Subscription Info
                'subscription_plan' => $tenant->subscription_plan,
                'subscription_status' => $tenant->subscription_status,
                'monthly_shipment_limit' => $tenant->monthly_shipment_limit,
                'current_month_shipments' => $tenant->getCurrentMonthShipments(),
            ],
            
            // Available courier options (all managed via WordPress plugin)
            'courier_options' => [
                'acs' => [
                    'name' => 'ACS Courier',
                    'logo' => '🚚',
                    'status' => 'managed_via_wordpress',
                    'description' => 'Greek courier service with real-time tracking (configured via WordPress plugin)',
                ],
                'speedex' => [
                    'name' => 'Speedex',
                    'logo' => '📦',
                    'status' => 'managed_via_wordpress',
                    'description' => 'Fast delivery service (configured via WordPress plugin)',
                ],
                'elta' => [
                    'name' => 'ΕΛΤΑ Courier',
                    'logo' => '📮',
                    'status' => 'managed_via_wordpress',
                    'description' => 'Greek postal service (configured via WordPress plugin)',
                ],
                'geniki' => [
                    'name' => 'Geniki Taxydromiki',
                    'logo' => '🚛',
                    'status' => 'managed_via_wordpress',
                    'description' => 'Express delivery service (configured via WordPress plugin)',
                ],
            ],
            
            'business_types' => [
                'retail' => 'Retail Store',
                'wholesale' => 'Wholesale',
                'marketplace' => 'Marketplace',
                'dropshipping' => 'Dropshipping',
                'services' => 'Services',
                'other' => 'Other',
            ],
            
            'currency_options' => [
                'EUR' => '€ Euro',
                'USD' => '$ US Dollar',
                'GBP' => '£ British Pound',
            ],
        ]);
    }

    /**
     * Update business information
     */
    public function updateBusiness(Request $request): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|string|in:retail,wholesale,marketplace,dropshipping,services,other',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'business_address' => 'nullable|string|max:500',
            'website_url' => 'nullable|url|max:255',
            'default_currency' => 'required|string|in:EUR,USD,GBP',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'shipping_cost' => 'required|numeric|min:0',
            'auto_create_shipments' => 'boolean',
            'send_notifications' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenant->update([
            'business_name' => $request->input('business_name'),
            'business_type' => $request->input('business_type'),
            'contact_email' => $request->input('contact_email'),
            'contact_phone' => $request->input('contact_phone'),
            'business_address' => $request->input('business_address'),
            'website_url' => $request->input('website_url'),
            'default_currency' => $request->input('default_currency'),
            'tax_rate' => $request->input('tax_rate'),
            'shipping_cost' => $request->input('shipping_cost'),
            'auto_create_shipments' => $request->boolean('auto_create_shipments'),
            'send_notifications' => $request->boolean('send_notifications'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Business settings updated successfully',
        ]);
    }

    // Note: ACS Courier credentials are now managed through the WordPress plugin

    // Note: Courier credentials are now managed through the WordPress plugin

    // Note: Courier API testing is now handled through the WordPress plugin

    /**
     * Generate new API token
     */
    public function generateApiToken(): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No tenant found. Please ensure you are properly authenticated.',
            ], 422);
        }
        
        $newToken = $tenant->generateApiToken();

        return response()->json([
            'success' => true,
            'message' => 'New API token generated successfully',
            'api_token' => $newToken,
        ]);
    }

    /**
     * Update webhook settings
     */
    public function updateWebhooks(Request $request): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $validator = Validator::make($request->all(), [
            'webhook_url' => 'nullable|url|max:255',
            'webhook_secret' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenant->update([
            'webhook_url' => $request->input('webhook_url'),
            'webhook_secret' => $request->input('webhook_secret'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook settings updated successfully',
        ]);
    }

    // Note: Courier credential management is now handled through the WordPress plugin

    /**
     * Download WordPress plugin as zip file
     */
    public function downloadPlugin(Request $request): JsonResponse
    {
        try {
            $tenant = Auth::user()->currentTenant();
            
            // Create a temporary directory for the plugin files
            $tempDir = storage_path('app/temp/plugin-' . uniqid());
            File::makeDirectory($tempDir, 0755, true);
            
            // Copy the plugin file to the temp directory
            $pluginSourcePath = base_path('dm-delivery-bridge.php');
            $pluginDestPath = $tempDir . '/dm-delivery-bridge.php';
            
            if (!File::exists($pluginSourcePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plugin file not found',
                ], 404);
            }
            
            File::copy($pluginSourcePath, $pluginDestPath);
            
            // Create a README file with installation instructions
            $readmeContent = $this->generatePluginReadme($tenant);
            File::put($tempDir . '/README.txt', $readmeContent);
            
            // Create zip file
            $zipFileName = 'dmm-delivery-bridge-' . date('Y-m-d') . '.zip';
            $zipPath = storage_path('app/temp/' . $zipFileName);
            
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create zip file',
                ], 500);
            }
            
            // Add files to zip
            $zip->addFile($pluginDestPath, 'dm-delivery-bridge.php');
            $zip->addFile($tempDir . '/README.txt', 'README.txt');
            
            $zip->close();
            
            // Clean up temp directory
            File::deleteDirectory($tempDir);
            
            // Generate a temporary download URL
            $downloadUrl = route('settings.download.plugin.file', ['filename' => $zipFileName]);
            
            return response()->json([
                'success' => true,
                'message' => 'Plugin zip file created successfully',
                'download_url' => $downloadUrl,
                'filename' => $zipFileName,
            ]);
            
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($tempDir) && File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create plugin zip: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Generate README content for the plugin
     */
    private function generatePluginReadme($tenant): string
    {
        $apiEndpoint = url('/api/woocommerce/order');
        
        return "DMM Delivery Bridge WordPress Plugin
============================================

INSTALLATION:
1. Upload the dm-delivery-bridge.php file to your WordPress plugins directory
2. Activate the plugin in your WordPress admin
3. Go to WooCommerce > DMM Delivery to configure the plugin

CONFIGURATION:
- API Endpoint: {$apiEndpoint}
- Tenant ID: {$tenant->id}
- API Key: [Generate from your DMM Delivery dashboard]

FEATURES:
- Automatic order synchronization with DMM Delivery
- WooCommerce integration
- Admin interface for configuration
- Bulk order processing tools
- Debug and logging features
- Support for multiple courier services

SUPPORT:
For support and updates, visit your DMM Delivery dashboard.

Generated on: " . date('Y-m-d H:i:s');
    }
    
    /**
     * Serve the plugin zip file for download
     */
    public function downloadPluginFile(Request $request, string $filename)
    {
        $tenant = Auth::user()->currentTenant();
        
        // Validate filename to prevent directory traversal
        if (!preg_match('/^dmm-delivery-bridge-\d{4}-\d{2}-\d{2}\.zip$/', $filename)) {
            abort(400, 'Invalid filename');
        }
        
        $filePath = storage_path('app/temp/' . $filename);
        
        if (!File::exists($filePath)) {
            abort(404, 'File not found');
        }
        
        // Clean up the file after serving (optional - you might want to keep it for a while)
        $response = response()->download($filePath, 'dmm-delivery-bridge.zip');
        
        // Schedule cleanup after response is sent
        register_shutdown_function(function() use ($filePath) {
            if (File::exists($filePath)) {
                File::delete($filePath);
            }
        });
        
        return $response;
    }
} 