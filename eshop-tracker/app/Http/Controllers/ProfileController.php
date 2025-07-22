<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $tenant = $user->tenant;

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'acs_api_key' => $tenant->acs_api_key,
                'acs_company_id' => $tenant->acs_company_id,
                'acs_company_password' => $tenant->acs_company_password,
                'acs_user_id' => $tenant->acs_user_id,
                'acs_user_password' => $tenant->acs_user_password,
                'has_acs_credentials' => $tenant->hasACSCredentials(),
            ] : null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Update the tenant's ACS credentials.
     */
    public function updateACSCredentials(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'acs_api_key' => 'nullable|string|max:255',
            'acs_company_id' => 'nullable|string|max:100',
            'acs_company_password' => 'nullable|string|max:255',
            'acs_user_id' => 'nullable|string|max:100',
            'acs_user_password' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return back()->withErrors(['tenant' => 'No tenant associated with this user.']);
        }

        // Update only the ACS-related fields for security
        $tenant->update([
            'acs_api_key' => $validated['acs_api_key'],
            'acs_company_id' => $validated['acs_company_id'],
            'acs_company_password' => $validated['acs_company_password'],
            'acs_user_id' => $validated['acs_user_id'],
            'acs_user_password' => $validated['acs_user_password'],
        ]);

        return back()->with('status', 'acs-credentials-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
