<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Contracts\WebSocketServiceInterface;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;
use Pusher\PusherException;

class WebSocketService implements WebSocketServiceInterface
{
    private Pusher $pusher;
    private string $channelPrefix;

    public function __construct()
    {
        $this->channelPrefix = config('app.name', 'dmmelivery') . '_';
        
        try {
            $this->pusher = new Pusher(
                config('broadcasting.connections.pusher.key'),
                config('broadcasting.connections.pusher.secret'),
                config('broadcasting.connections.pusher.app_id'),
                [
                    'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                    'useTLS' => true,
                    'encrypted' => true,
                ]
            );
        } catch (PusherException $e) {
            Log::error('Failed to initialize Pusher', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Broadcast shipment status update
     */
    public function broadcastShipmentUpdate(Shipment $shipment, array $updateData = []): void
    {
        try {
            $channel = $this->getTenantChannel($shipment->tenant_id);
            $event = 'shipment.updated';

            $data = [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'status' => $shipment->status,
                'updated_at' => $shipment->updated_at->toISOString(),
                'customer_name' => $shipment->customer?->name,
                'courier_name' => $shipment->courier?->name,
                'update_data' => $updateData,
            ];

            $this->pusher->trigger($channel, $event, $data);

            Log::info('Shipment update broadcasted', [
                'shipment_id' => $shipment->id,
                'tenant_id' => $shipment->tenant_id,
                'channel' => $channel,
                'event' => $event
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast shipment update', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast new shipment created
     */
    public function broadcastNewShipment(Shipment $shipment): void
    {
        try {
            $channel = $this->getTenantChannel($shipment->tenant_id);
            $event = 'shipment.created';

            $data = [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'status' => $shipment->status,
                'created_at' => $shipment->created_at->toISOString(),
                'customer_name' => $shipment->customer?->name,
                'courier_name' => $shipment->courier?->name,
                'shipping_address' => $shipment->shipping_address,
            ];

            $this->pusher->trigger($channel, $event, $data);

            Log::info('New shipment broadcasted', [
                'shipment_id' => $shipment->id,
                'tenant_id' => $shipment->tenant_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast new shipment', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast shipment delivered
     */
    public function broadcastShipmentDelivered(Shipment $shipment): void
    {
        try {
            $channel = $this->getTenantChannel($shipment->tenant_id);
            $event = 'shipment.delivered';

            $data = [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'delivered_at' => $shipment->actual_delivery?->toISOString(),
                'customer_name' => $shipment->customer?->name,
                'courier_name' => $shipment->courier?->name,
                'delivery_time' => $shipment->actual_delivery?->diffForHumans(),
            ];

            $this->pusher->trigger($channel, $event, $data);

            Log::info('Shipment delivered broadcasted', [
                'shipment_id' => $shipment->id,
                'tenant_id' => $shipment->tenant_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast shipment delivered', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast alert triggered
     */
    public function broadcastAlert(\App\Models\Alert $alert): void
    {
        try {
            $channel = $this->getTenantChannel($alert->tenant_id);
            $event = 'alert.triggered';

            $data = [
                'alert_id' => $alert->id,
                'title' => $alert->title,
                'description' => $alert->description,
                'alert_type' => $alert->alert_type,
                'severity_level' => $alert->severity_level,
                'shipment_id' => $alert->shipment_id,
                'tracking_number' => $alert->shipment?->tracking_number,
                'triggered_at' => $alert->triggered_at->toISOString(),
            ];

            $this->pusher->trigger($channel, $event, $data);

            Log::info('Alert broadcasted', [
                'alert_id' => $alert->id,
                'tenant_id' => $alert->tenant_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast alert', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast dashboard statistics update
     */
    public function broadcastDashboardUpdate(string $tenantId, array $stats): void
    {
        try {
            $channel = $this->getTenantChannel($tenantId);
            $event = 'dashboard.updated';

            $this->pusher->trigger($channel, $event, $stats);

            Log::info('Dashboard update broadcasted', [
                'tenant_id' => $tenantId,
                'stats_keys' => array_keys($stats)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast dashboard update', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast courier status update
     */
    public function broadcastCourierUpdate(string $tenantId, array $courierData): void
    {
        try {
            $channel = $this->getTenantChannel($tenantId);
            $event = 'courier.updated';

            $this->pusher->trigger($channel, $event, $courierData);

            Log::info('Courier update broadcasted', [
                'tenant_id' => $tenantId,
                'courier_id' => $courierData['courier_id'] ?? 'unknown'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast courier update', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get tenant-specific channel name
     */
    private function getTenantChannel(string $tenantId): string
    {
        return $this->channelPrefix . 'tenant_' . $tenantId;
    }

    /**
     * Get user-specific channel name
     */
    public function getUserChannel(string $userId): string
    {
        return $this->channelPrefix . 'user_' . $userId;
    }

    /**
     * Broadcast to specific user
     */
    public function broadcastToUser(string $userId, string $event, array $data): void
    {
        try {
            $channel = $this->getUserChannel($userId);
            $this->pusher->trigger($channel, $event, $data);

            Log::info('User-specific broadcast sent', [
                'user_id' => $userId,
                'event' => $event
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast to user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast system-wide notification
     */
    public function broadcastSystemNotification(string $tenantId, string $message, string $type = 'info'): void
    {
        try {
            $channel = $this->getTenantChannel($tenantId);
            $event = 'system.notification';

            $data = [
                'message' => $message,
                'type' => $type,
                'timestamp' => now()->toISOString(),
            ];

            $this->pusher->trigger($channel, $event, $data);

            Log::info('System notification broadcasted', [
                'tenant_id' => $tenantId,
                'type' => $type
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast system notification', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get channel authentication data
     */
    public function getChannelAuth(string $channel, string $socketId): array
    {
        try {
            $auth = $this->pusher->socket_auth($channel, $socketId);
            return ['auth' => $auth];
        } catch (\Exception $e) {
            Log::error('Failed to authenticate channel', [
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Authentication failed'];
        }
    }

    /**
     * Test WebSocket connection
     */
    public function testConnection(): bool
    {
        try {
            $this->pusher->trigger('test-channel', 'test-event', ['message' => 'test']);
            return true;
        } catch (\Exception $e) {
            Log::error('WebSocket connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
