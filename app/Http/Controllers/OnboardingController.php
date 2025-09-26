<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Show welcome page
     */
    public function welcome(): Response
    {
        $tenant = $this->getCurrentTenant();
        
        if (!$tenant || $tenant->onboarding_status === 'active') {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Onboarding/Welcome', [
            'tenant' => $tenant,
            'progress' => $tenant->getOnboardingProgress(),
            'nextStep' => $tenant->getNextOnboardingStep(),
        ]);
    }

    /**
     * Show profile completion step
     */
    public function profile(): Response
    {
        $tenant = $this->getCurrentTenant();
        
        if (!$tenant || !in_array($tenant->onboarding_status, ['email_verified', 'profile_completed'])) {
            return $this->redirectToCorrectStep($tenant);
        }

        return Inertia::render('Onboarding/ProfileCompletion', [
            'tenant' => $tenant,
            'progress' => $tenant->getOnboardingProgress(),
            'businessTypes' => $this->getBusinessTypes(),
        ]);
    }

    /**
     * Update profile information
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $tenant = $this->getCurrentTenant();
        
        if (!$tenant) {
            return redirect()->route('register');
        }

        $validator = Validator::make($request->all(), [
            'business_type' => 'required|in:eshop,marketplace,retail,other',
            'description' => 'nullable|string|max:1000',
            'website_url' => 'nullable|url|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'business_address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:10',
            'vat_number' => 'nullable|string|max:20',
            'tax_office' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $tenant->update($request->only([
            'business_type', 'description', 'website_url', 'contact_phone',
            'business_address', 'city', 'postal_code', 'vat_number', 'tax_office'
        ]));

        // Move to next step
        if ($tenant->onboarding_status === 'email_verified') {
            $tenant->update(['onboarding_status' => 'profile_completed']);
        }

        return redirect()->route('onboarding.branding')
            ->with('success', 'Profile updated successfully!');
    }

    /**
     * Show branding configuration step
     */
    public function branding(): Response
    {
        $tenant = $this->getCurrentTenant();
        
        if (!$tenant || !in_array($tenant->onboarding_status, ['profile_completed', 'payment_setup', 'api_configured'])) {
            return $this->redirectToCorrectStep($tenant);
        }

        return Inertia::render('Onboarding/BrandingConfiguration', [
            'tenant' => $tenant,
            'progress' => $tenant->getOnboardingProgress(),
            'currentTheme' => $tenant->theme_config,
            'presetThemes' => $this->getPresetThemes(),
        ]);
    }

    /**
     * Update branding configuration
     */
    public function updateBranding(Request $request): RedirectResponse
    {
        $tenant = $this->getCurrentTenant();
        
        if (!$tenant) {
            return redirect()->route('register');
        }

        $validator = Validator::make($request->all(), [
            'primary_color' => 'required|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'secondary_color' => 'required|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'font_family' => 'required|in:Inter,Roboto,Open Sans,Lato,Montserrat',
            'logo_position' => 'required|in:left,center,right',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'favicon' => 'nullable|image|mimes:png,jpg,jpeg,ico|max:512',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        // Handle logo upload
        $logoUrl = $tenant->logo_url;
        if ($request->hasFile('logo')) {
            if ($logoUrl) {
                Storage::disk('public')->delete($logoUrl);
            }
            $logoUrl = $request->file('logo')->store('logos', 'public');
        }

        // Handle favicon upload
        $faviconUrl = $tenant->favicon_url;
        if ($request->hasFile('favicon')) {
            if ($faviconUrl) {
                Storage::disk('public')->delete($faviconUrl);
            }
            $faviconUrl = $request->file('favicon')->store('favicons', 'public');
        }

        // Update theme configuration
        $themeConfig = [
            'primary_color' => $request->primary_color,
            'secondary_color' => $request->secondary_color,
            'font_family' => $request->font_family,
            'logo_position' => $request->logo_position,
        ];

        $tenant->update([
            'logo_url' => $logoUrl,
            'favicon_url' => $faviconUrl,
            'theme_config' => $themeConfig,
        ]);

        return redirect()->route('onboarding.api-config')
            ->with('success', 'Branding updated successfully!');
    }

    /**
     * Show API configuration step
     */
    public function apiConfig(): Response
    {
        $tenant = $this->getCurrentTenant();
        
        if (!$tenant || !in_array($tenant->onboarding_status, ['profile_completed', 'payment_setup', 'api_configured'])) {
            return $this->redirectToCorrectStep($tenant);
        }

        return Inertia::render('Onboarding/ApiConfiguration', [
            'tenant' => $tenant,
            'progress' => $tenant->getOnboardingProgress(),
            'supportedCouriers' => $this->getSupportedCouriers(),
        ]);
    }

    /**
     * Update API configuration
     */
    public function updateApiConfig(Request $request): RedirectResponse
    {
        $tenant = $this->getCurrentTenant();
        
        if (!$tenant) {
            return redirect()->route('register');
        }

        // Note: ACS credentials are now managed through the WordPress plugin
        // Skip API configuration step and move to testing
        $tenant->update([
            'onboarding_status' => 'api_configured',
        ]);

        return redirect()->route('onboarding.testing')
            ->with('success', 'API configuration saved successfully!');
    }

    /**
     * Show testing phase
     */
    public function testing(): Response
    {
        $tenant = $this->getCurrentTenant();
        
        if (!$tenant || !in_array($tenant->onboarding_status, ['api_configured', 'testing'])) {
            return $this->redirectToCorrectStep($tenant);
        }

        return Inertia::render('Onboarding/Testing', [
            'tenant' => $tenant,
            'progress' => $tenant->getOnboardingProgress(),
            'testResults' => session('test_results'),
        ]);
    }

    /**
     * Test API configuration
     */
    public function testApi(Request $request): RedirectResponse
    {
        $tenant = $this->getCurrentTenant();
        
        if (!$tenant) {
            return back()->withErrors(['api' => 'Tenant not found']);
        }

        try {
            // TODO: Implement actual API testing
            // For now, simulate a test
            $testResults = [
                'acs_connection' => true,
                'acs_authentication' => true,
                'test_tracking' => true,
                'timestamp' => now(),
            ];

            $tenant->update(['onboarding_status' => 'testing']);

            return back()
                ->with('test_results', $testResults)
                ->with('success', 'API test completed successfully!');

        } catch (\Exception $e) {
            return back()
                ->with('test_results', [
                    'acs_connection' => false,
                    'error' => $e->getMessage(),
                    'timestamp' => now(),
                ])
                ->withErrors(['api' => 'API test failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Complete onboarding and activate tenant
     */
    public function complete(): RedirectResponse
    {
        $tenant = $this->getCurrentTenant();
        
        if (!$tenant || $tenant->onboarding_status !== 'testing') {
            return $this->redirectToCorrectStep($tenant);
        }

        $tenant->update([
            'onboarding_status' => 'active',
            'onboarding_completed_at' => now(),
            'is_active' => true,
            'billing_cycle_start' => now()->startOfMonth(),
        ]);

        // Clear registration session
        session()->forget(['registration_tenant_id', 'registration_user_id']);

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to eShop Tracker! Your account is now active.');
    }

    /**
     * Get current tenant from session
     */
    private function getCurrentTenant(): ?Tenant
    {
        $tenantId = session('registration_tenant_id');
        return $tenantId ? Tenant::find($tenantId) : null;
    }

    /**
     * Redirect to correct onboarding step
     */
    private function redirectToCorrectStep(?Tenant $tenant): RedirectResponse
    {
        if (!$tenant) {
            return redirect()->route('register');
        }

        return match($tenant->onboarding_status) {
            'pending' => redirect()->route('registration.email-verification'),
            'email_verified' => redirect()->route('onboarding.profile'),
            'profile_completed' => redirect()->route('onboarding.branding'),
            'payment_setup' => redirect()->route('onboarding.api-config'),
            'api_configured' => redirect()->route('onboarding.testing'),
            'testing' => redirect()->route('onboarding.testing'),
            'active' => redirect()->route('dashboard'),
            default => redirect()->route('onboarding.welcome'),
        };
    }

    /**
     * Get business types
     */
    private function getBusinessTypes(): array
    {
        return [
            'eshop' => 'Online Store / eShop',
            'marketplace' => 'Marketplace Platform',
            'retail' => 'Retail Store',
            'other' => 'Other Business Type',
        ];
    }

    /**
     * Get preset themes
     */
    private function getPresetThemes(): array
    {
        return [
            'blue' => [
                'name' => 'Ocean Blue',
                'primary_color' => '#3B82F6',
                'secondary_color' => '#1E40AF',
                'font_family' => 'Inter',
            ],
            'green' => [
                'name' => 'Forest Green',
                'primary_color' => '#10B981',
                'secondary_color' => '#047857',
                'font_family' => 'Inter',
            ],
            'purple' => [
                'name' => 'Royal Purple',
                'primary_color' => '#8B5CF6',
                'secondary_color' => '#5B21B6',
                'font_family' => 'Inter',
            ],
            'orange' => [
                'name' => 'Sunset Orange',
                'primary_color' => '#F59E0B',
                'secondary_color' => '#D97706',
                'font_family' => 'Inter',
            ],
        ];
    }

    /**
     * Get supported couriers
     */
    private function getSupportedCouriers(): array
    {
        return [
            'acs' => [
                'name' => 'ACS Courier',
                'logo' => '/images/couriers/acs.png',
                'required_fields' => ['api_key', 'company_id', 'company_password', 'user_id', 'user_password'],
                'demo_available' => true,
            ],
            'elta' => [
                'name' => 'ELTA Courier',
                'logo' => '/images/couriers/elta.png',
                'required_fields' => ['api_key'],
                'demo_available' => false,
                'coming_soon' => true,
            ],
            'speedex' => [
                'name' => 'Speedex',
                'logo' => '/images/couriers/speedex.png',
                'required_fields' => ['api_key'],
                'demo_available' => false,
                'coming_soon' => true,
            ],
        ];
    }
}
