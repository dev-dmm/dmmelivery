<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantRegistrationController extends Controller
{
    /**
     * Show the registration form
     */
    public function showRegistrationForm(): Response
    {
        return Inertia::render('Registration/EShopRegister', [
            'businessTypes' => $this->getBusinessTypes(),
            'subscriptionPlans' => $this->getSubscriptionPlans(),
        ]);
    }

    /**
     * Handle the registration submission
     */
    public function register(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            // Business Information
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|in:eshop,marketplace,retail,other',
            'description' => 'nullable|string|max:1000',
            'website_url' => 'nullable|url|max:255',
            'subdomain' => 'required|string|max:50|alpha_dash|unique:tenants,subdomain',
            
            // Contact Information
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255|unique:tenants,contact_email',
            'contact_phone' => 'nullable|string|max:20',
            
            // Address Information
            'business_address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:10',
            'country' => 'required|string|size:2',
            
            // Tax Information
            'vat_number' => 'nullable|string|max:20',
            'tax_office' => 'nullable|string|max:100',
            
            // Admin User Information
            'admin_first_name' => 'required|string|max:255',
            'admin_last_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
            
            // ACS API Credentials (optional at registration)
            'acs_api_key' => 'nullable|string|max:255',
            'acs_company_id' => 'nullable|string|max:100',
            'acs_company_password' => 'nullable|string|max:255',
            'acs_user_id' => 'nullable|string|max:100',
            'acs_user_password' => 'nullable|string|max:255',
            
            // Subscription Plan
            'subscription_plan' => 'required|in:free,starter,business,enterprise',
            
            // Terms & Conditions
            'terms_accepted' => 'required|accepted',
            'privacy_accepted' => 'required|accepted',
        ], [
            'subdomain.unique' => 'This subdomain is already taken. Please choose another one.',
            'contact_email.unique' => 'This email is already registered. Please use a different email.',
            'admin_email.unique' => 'This email is already registered as an admin user.',
            'terms_accepted.accepted' => 'You must accept the Terms & Conditions.',
            'privacy_accepted.accepted' => 'You must accept the Privacy Policy.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        
        try {
            // Create the tenant
            $tenant = Tenant::create([
                'name' => $request->business_name,
                'subdomain' => strtolower($request->subdomain),
                'business_type' => $request->business_type,
                'description' => $request->description,
                'website_url' => $request->website_url,
                
                // Contact Information
                'contact_name' => $request->contact_name,
                'contact_email' => $request->contact_email,
                'contact_phone' => $request->contact_phone,
                
                // Address Information
                'business_address' => $request->business_address,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'country' => $request->country,
                
                // Tax Information
                'vat_number' => $request->vat_number,
                'tax_office' => $request->tax_office,
                
                // ACS API Credentials
                'acs_api_key' => $request->acs_api_key,
                'acs_company_id' => $request->acs_company_id,
                'acs_company_password' => $request->acs_company_password,
                'acs_user_id' => $request->acs_user_id,
                'acs_user_password' => $request->acs_user_password,
                
                // Subscription & Status
                'subscription_plan' => $request->subscription_plan,
                'monthly_shipment_limit' => $this->getShipmentLimit($request->subscription_plan),
                'onboarding_status' => 'pending',
                'onboarding_started_at' => now(),
                'is_active' => false,
                
                // Default Features
                'enabled_features' => $this->getDefaultFeatures($request->subscription_plan),
                
                // Default Theme
                'theme_config' => [
                    'primary_color' => '#3B82F6',
                    'secondary_color' => '#6B7280',
                    'logo_position' => 'left',
                    'font_family' => 'Inter',
                ],
            ]);

            // Create the admin user
            $adminUser = User::create([
                'tenant_id' => $tenant->id,
                'first_name' => $request->admin_first_name,
                'last_name' => $request->admin_last_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'role' => 'admin',
                'is_active' => true,
            ]);

            // Generate API token for the tenant
            $apiToken = $tenant->generateApiToken();

            DB::commit();

            // Send welcome email (you can implement this later)
            // $this->sendWelcomeEmail($tenant, $adminUser, $apiToken);

            // Store the tenant and user in session for onboarding
            session([
                'registration_tenant_id' => $tenant->id,
                'registration_user_id' => $adminUser->id,
            ]);

            return redirect()->route('onboarding.welcome')
                ->with('success', 'Registration successful! Please check your email for verification.');

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()
                ->withErrors(['registration' => 'Registration failed. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Show email verification page
     */
    public function showEmailVerification(Request $request): Response
    {
        $tenantId = session('registration_tenant_id');
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        if (!$tenant) {
            return redirect()->route('register');
        }

        return Inertia::render('Registration/EmailVerification', [
            'tenant' => $tenant,
            'email' => $tenant->contact_email,
        ]);
    }

    /**
     * Handle email verification
     */
    public function verifyEmail(Request $request, string $token): RedirectResponse
    {
        // In a real implementation, you'd store verification tokens
        // For now, we'll just mark the email as verified
        
        $tenantId = session('registration_tenant_id');
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        if (!$tenant) {
            return redirect()->route('register')
                ->withErrors(['verification' => 'Invalid verification link.']);
        }

        $tenant->update([
            'email_verified_at' => now(),
            'onboarding_status' => 'email_verified',
        ]);

        return redirect()->route('onboarding.profile')
            ->with('success', 'Email verified successfully!');
    }

    /**
     * Check subdomain availability
     */
    public function checkSubdomain(Request $request)
    {
        $subdomain = strtolower($request->input('subdomain'));
        
        if (empty($subdomain)) {
            return response()->json(['available' => false, 'message' => 'Subdomain is required']);
        }

        if (!preg_match('/^[a-z0-9-]+$/', $subdomain)) {
            return response()->json(['available' => false, 'message' => 'Subdomain can only contain letters, numbers, and hyphens']);
        }

        $exists = Tenant::where('subdomain', $subdomain)->exists();
        
        return response()->json([
            'available' => !$exists,
            'message' => $exists ? 'This subdomain is already taken' : 'Subdomain is available',
            'preview_url' => $subdomain . '.eshoptracker.gr'
        ]);
    }

    /**
     * Get available business types
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
     * Get available subscription plans
     */
    private function getSubscriptionPlans(): array
    {
        return [
            'free' => [
                'name' => 'Free',
                'price' => 0,
                'currency' => 'EUR',
                'shipments' => 100,
                'features' => ['Basic tracking', 'Email notifications', 'Standard support'],
                'recommended' => false,
            ],
            'starter' => [
                'name' => 'Starter',
                'price' => 29,
                'currency' => 'EUR',
                'shipments' => 500,
                'features' => ['All Free features', 'SMS notifications', 'Priority support', 'Custom branding'],
                'recommended' => true,
            ],
            'business' => [
                'name' => 'Business',
                'price' => 79,
                'currency' => 'EUR',
                'shipments' => 2000,
                'features' => ['All Starter features', 'API access', 'Webhooks', 'Analytics dashboard'],
                'recommended' => false,
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'price' => 199,
                'currency' => 'EUR',
                'shipments' => 'unlimited',
                'features' => ['All Business features', 'Dedicated support', 'Custom integrations', 'White-label'],
                'recommended' => false,
            ],
        ];
    }

    /**
     * Get shipment limit based on plan
     */
    private function getShipmentLimit(string $plan): int
    {
        $limits = [
            'free' => 100,
            'starter' => 500,
            'business' => 2000,
            'enterprise' => 999999, // Practically unlimited
        ];

        return $limits[$plan] ?? 100;
    }

    /**
     * Get default features based on plan
     */
    private function getDefaultFeatures(string $plan): array
    {
        $allFeatures = [
            'basic_tracking',
            'email_notifications',
            'sms_notifications',
            'custom_branding',
            'api_access',
            'webhooks',
            'analytics',
            'priority_support',
            'white_label',
        ];

        $planFeatures = [
            'free' => ['basic_tracking', 'email_notifications'],
            'starter' => ['basic_tracking', 'email_notifications', 'sms_notifications', 'custom_branding'],
            'business' => ['basic_tracking', 'email_notifications', 'sms_notifications', 'custom_branding', 'api_access', 'webhooks', 'analytics'],
            'enterprise' => $allFeatures,
        ];

        return $planFeatures[$plan] ?? $planFeatures['free'];
    }

    /**
     * Send welcome email (to be implemented)
     */
    private function sendWelcomeEmail(Tenant $tenant, User $user, string $apiToken): void
    {
        // TODO: Implement welcome email with verification link
        // Mail::to($tenant->contact_email)->send(new WelcomeEmail($tenant, $user, $apiToken));
    }
}
