<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ACSTestController extends Controller
{
    /**
     * Update ACS credentials for current user's tenant
     */
    public function updateCredentials(Request $request): JsonResponse
    {
        $request->validate([
            'acs_api_key' => 'nullable|string|max:255',
            'acs_company_id' => 'nullable|string|max:100', 
            'acs_company_password' => 'nullable|string|max:255',
            'acs_user_id' => 'nullable|string|max:100',
            'acs_user_password' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        if (!$user || !$user->tenant) {
            return response()->json(['error' => 'No tenant found'], 404);
        }

        $tenant = $user->tenant;
        $tenant->update([
            'acs_api_key' => $request->acs_api_key,
            'acs_company_id' => $request->acs_company_id,
            'acs_company_password' => $request->acs_company_password,
            'acs_user_id' => $request->acs_user_id,
            'acs_user_password' => $request->acs_user_password,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ACS credentials updated successfully!',
            'credentials' => [
                'acs_api_key' => $tenant->acs_api_key,
                'acs_company_id' => $tenant->acs_company_id,
                'has_credentials' => $tenant->hasACSCredentials(),
            ]
        ]);
    }

    /**
     * Get current ACS credentials
     */
    public function getCredentials(): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !$user->tenant) {
            return response()->json(['error' => 'No tenant found'], 404);
        }

        $tenant = $user->tenant;
        
        return response()->json([
            'tenant_name' => $tenant->name,
            'credentials' => [
                'acs_api_key' => $tenant->acs_api_key,
                'acs_company_id' => $tenant->acs_company_id,
                'acs_user_id' => $tenant->acs_user_id,
                'has_credentials' => $tenant->hasACSCredentials(),
            ]
        ]);
    }
}
