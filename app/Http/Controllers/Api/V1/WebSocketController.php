<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Contracts\WebSocketServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WebSocketController extends Controller
{
    private WebSocketServiceInterface $webSocketService;

    public function __construct(WebSocketServiceInterface $webSocketService)
    {
        $this->webSocketService = $webSocketService;
    }

    /**
     * Authenticate WebSocket connection
     */
    public function authenticate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'socket_id' => 'required|string',
            'channel_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Validate channel access
        $channelName = $request->channel_name;
        if (!$this->canAccessChannel($channelName, $tenantId, $user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied to channel',
            ], 403);
        }

        // Generate authentication signature
        $authSignature = $this->generateAuthSignature(
            $request->socket_id,
            $channelName,
            $user->id
        );

        return response()->json([
            'success' => true,
            'auth' => $authSignature,
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Get WebSocket connection status
     */
    public function status(): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'channels' => $this->getAvailableChannels($tenantId, $user->id),
                'connection_status' => 'ready',
                'timestamp' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * Get available channels for user
     */
    public function channels(): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $channels = $this->getAvailableChannels($tenantId, $user->id);

        return response()->json([
            'success' => true,
            'data' => $channels,
        ]);
    }

    /**
     * Broadcast message to channel
     */
    public function broadcast(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'channel' => 'required|string',
            'event' => 'required|string',
            'data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Validate channel access
        if (!$this->canAccessChannel($request->channel, $tenantId, $user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied to channel',
            ], 403);
        }

        try {
            $this->webSocketService->broadcastToChannel(
                $request->channel,
                $request->event,
                $request->data
            );

            return response()->json([
                'success' => true,
                'message' => 'Message broadcasted successfully',
                'channel' => $request->channel,
                'event' => $request->event,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to broadcast message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get WebSocket configuration
     */
    public function config(): JsonResponse
    {
        $config = [
            'pusher_key' => config('broadcasting.connections.pusher.key'),
            'pusher_cluster' => config('broadcasting.connections.pusher.options.cluster'),
            'pusher_host' => config('broadcasting.connections.pusher.options.host'),
            'pusher_port' => config('broadcasting.connections.pusher.options.port'),
            'pusher_encrypted' => config('broadcasting.connections.pusher.options.encrypted'),
        ];

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Check if user can access channel
     */
    private function canAccessChannel(string $channelName, string $tenantId, string $userId): bool
    {
        // Private channels for tenant
        if (str_starts_with($channelName, 'private-tenant.')) {
            $channelTenantId = str_replace('private-tenant.', '', $channelName);
            return $channelTenantId === $tenantId;
        }

        // Private channels for user
        if (str_starts_with($channelName, 'private-user.')) {
            $channelUserId = str_replace('private-user.', '', $channelName);
            return $channelUserId === $userId;
        }

        // Public channels
        if (str_starts_with($channelName, 'public-tenant.')) {
            $channelTenantId = str_replace('public-tenant.', '', $channelName);
            return $channelTenantId === $tenantId;
        }

        return false;
    }

    /**
     * Generate authentication signature
     */
    private function generateAuthSignature(string $socketId, string $channelName, string $userId): string
    {
        $secret = config('broadcasting.connections.pusher.secret');
        $stringToSign = $socketId . ':' . $channelName;
        $signature = hash_hmac('sha256', $stringToSign, $secret, true);
        
        return config('broadcasting.connections.pusher.key') . ':' . base64_encode($signature);
    }

    /**
     * Get available channels for user
     */
    private function getAvailableChannels(string $tenantId, string $userId): array
    {
        return [
            'tenant_channels' => [
                'public-tenant.' . $tenantId,
                'private-tenant.' . $tenantId,
            ],
            'user_channels' => [
                'private-user.' . $userId,
            ],
            'system_channels' => [
                'public-system',
            ],
        ];
    }
}
