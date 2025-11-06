<?php

namespace App\Services\Contracts;

use App\Models\Shipment;
use App\Models\Tenant;
use App\Models\User;

interface WebSocketServiceInterface
{
    /**
     * Broadcast shipment status update
     */
    public function broadcastShipmentUpdate(Shipment $shipment, array $updateData = []): void;

    /**
     * Broadcast new shipment
     */
    public function broadcastNewShipment(Shipment $shipment): void;

    /**
     * Broadcast shipment delivered
     */
    public function broadcastShipmentDelivered(Shipment $shipment): void;

    /**
     * Broadcast alert
     */
    public function broadcastAlert(\App\Models\Alert $alert): void;

    /**
     * Broadcast dashboard update
     */
    public function broadcastDashboardUpdate(string $tenantId, array $stats): void;

    /**
     * Broadcast courier update
     */
    public function broadcastCourierUpdate(string $tenantId, array $courierData): void;

    /**
     * Get user channel name
     */
    public function getUserChannel(string $userId): string;

    /**
     * Broadcast to user
     */
    public function broadcastToUser(string $userId, string $event, array $data): void;

    /**
     * Broadcast system notification
     */
    public function broadcastSystemNotification(string $tenantId, string $message, string $type = 'info'): void;

    /**
     * Get channel authentication
     */
    public function getChannelAuth(string $channel, string $socketId): array;

    /**
     * Test connection
     */
    public function testConnection(): bool;
}

