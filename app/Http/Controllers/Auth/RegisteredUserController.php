<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Split name into first and last name
        $nameParts = explode(' ', $request->name, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        // Create a unique tenant for this user based on their name
        $companyName = $request->name . "'s Company";
        $subdomain = strtolower(str_replace(' ', '-', preg_replace('/[^a-zA-Z0-9\s]/', '', $request->name)));
        
        // Ensure subdomain is unique by appending a number if needed
        $originalSubdomain = $subdomain;
        $counter = 1;
        while (Tenant::where('subdomain', $subdomain)->exists()) {
            $subdomain = $originalSubdomain . '-' . $counter;
            $counter++;
        }
        
        $userTenant = Tenant::create([
            'name' => $companyName,
            'subdomain' => $subdomain,
            'is_active' => true,
            'onboarding_status' => 'pending',
        ]);

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tenant_id' => $userTenant->id,
            'role' => 'admin', // First user becomes admin
            'is_active' => true,
        ]);

        event(new Registered($user));

        Auth::login($user);

        $redirectRoute = ($user && $user->isSuperAdmin())
            ? route('super-admin.dashboard', absolute: false)
            : route('dashboard', absolute: false);

        return redirect($redirectRoute);
    }
}
