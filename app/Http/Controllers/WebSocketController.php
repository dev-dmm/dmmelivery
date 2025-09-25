<?php

namespace App\Http\Controllers;

use App\Services\WebSocketService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class WebSocketController extends Controller
{
    private WebSocketService $webSocketService;

    public function __construct(WebSocketService $webSocketService)
    {
        $this->webSocketService = $webSocketService;
    }

    /**
     * Authenticate WebSocket channel
     */
    public function authenticate(Request $request): JsonResponse
    {
        $request->validate([
            'socket_id' => 'required|string',
            'channel_name' => 'required|string',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');

        // Validate channel access
        if (!$this->canAccessChannel($user, $channelName)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $auth = $this->webSocketService->getChannelAuth($channelName, $socketId);
            return response()->json($auth);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed'], 500);
        }
    }

    /**
     * Check if user can access the channel
     */
    private function canAccessChannel($user, string $channelName): bool
    {
        // Allow access to tenant channels
        if (str_contains($channelName, 'tenant_' . $user->tenant_id)) {
            return true;
        }

        // Allow access to user-specific channels
        if (str_contains($channelName, 'user_' . $user->id)) {
            return true;
        }

        // Super admins can access all channels
        if ($user->isSuperAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Test WebSocket connection
     */
    public function test(): JsonResponse
    {
        $isConnected = $this->webSocketService->testConnection();
        
        return response()->json([
            'connected' => $isConnected,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get user's available channels
     */
    public function getChannels(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $channels = [
            'tenant' => 'tenant_' . $user->tenant_id,
            'user' => 'user_' . $user->id,
        ];

        return response()->json([
            'channels' => $channels,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }
}
