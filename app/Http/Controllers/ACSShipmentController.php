<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\Customer;
use App\Models\Courier;
use App\Models\ShipmentStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ACSShipmentController extends Controller
{
    /**
     * Handle ACS shipment from WordPress plugin
     */
    public function store(Request $request)
    {
        try {
            $orderData = $request->input('order');
            $shipmentData = $request->input('shipment');
            $courier = $request->input('courier', 'ACS');
            $source = $request->input('source', 'wordpress_acs_auto_detection');

            // Validate required data
            if (!$orderData || !$shipmentData) {
                return response()->json(['error' => 'Missing order or shipment data'], 400);
            }

            DB::beginTransaction();

            // Find or create customer
            $customer = $this->findOrCreateCustomer($orderData);

            // Find or create order
            $order = $this->findOrCreateOrder($orderData, $customer);

            // Find or create courier
            $courierModel = $this->findOrCreateCourier($courier);

            // Create or update shipment
            $shipment = $this->createOrUpdateShipment($order, $shipmentData, $courierModel);

            // Create status history entries from tracking events
            $this->createStatusHistory($shipment, $shipmentData['tracking_events'] ?? []);

            DB::commit();

            Log::info('ACS Shipment created successfully', [
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'source' => $source
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'message' => 'ACS shipment processed successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('ACS Shipment creation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'Failed to process ACS shipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Find or create customer
     */
    private function findOrCreateCustomer($orderData)
    {
        $email = $orderData['customer_email'] ?? null;
        $phone = $orderData['customer_phone'] ?? null;
        $name = $orderData['customer_name'] ?? 'Unknown Customer';
        
        // Check for existing customer by email OR phone
        $customer = null;
        if ($email || $phone) {
            $customer = Customer::where(function ($query) use ($email, $phone) {
                if ($email) {
                    $query->where('email', $email);
                }
                if ($phone) {
                    $query->orWhere('phone', $phone);
                }
            })->first();
        }

        if ($customer) {
            // Update existing customer with new information if provided
            $updateData = [];
            if ($name && $name !== $customer->name) {
                $updateData['name'] = $name;
            }
            if ($phone && $phone !== $customer->phone) {
                $updateData['phone'] = $phone;
            }
            if ($email && $email !== $customer->email) {
                $updateData['email'] = $email;
            }

            if (!empty($updateData)) {
                $customer->update($updateData);
            }
            
            return $customer;
        }

        return Customer::create([
            'name' => $name,
            'email' => $email ?? 'no-email@example.com',
            'phone' => $phone,
        ]);
    }

    /**
     * Find or create order
     */
    private function findOrCreateOrder($orderData, $customer)
    {
        $externalOrderId = $orderData['external_order_id'];
        
        $order = Order::where('external_order_id', $externalOrderId)->first();
        
        if ($order) {
            // Update existing order with ACS data
            $order->update([
                'status' => $orderData['status'] ?? $order->status,
                'customer_name' => $orderData['customer_name'] ?? $order->customer_name,
                'customer_email' => $orderData['customer_email'] ?? $order->customer_email,
                'customer_phone' => $orderData['customer_phone'] ?? $order->customer_phone,
                'shipping_address' => $orderData['shipping_address'] ?? $order->shipping_address,
                'billing_address' => $orderData['billing_address'] ?? $order->billing_address,
                'total_amount' => $orderData['total_amount'] ?? $order->total_amount,
                'currency' => $orderData['currency'] ?? $order->currency,
                'additional_data' => array_merge(
                    $order->additional_data ?? [],
                    $orderData['additional_data'] ?? []
                )
            ]);
            
            Log::info('ACS: Updated existing order', [
                'order_id' => $order->id,
                'external_order_id' => $externalOrderId,
                'status' => $order->status
            ]);
            
            return $order;
        }

        // Create new order (this should rarely happen as orders are usually created first)
        $order = Order::create([
            'tenant_id' => app('tenant')->id ?? 1, // Use current tenant
            'customer_id' => $customer->id,
            'external_order_id' => $externalOrderId,
            'order_number' => $orderData['order_number'] ?? $externalOrderId,
            'status' => $orderData['status'] ?? 'processing',
            'customer_name' => $orderData['customer_name'] ?? $customer->name,
            'customer_email' => $orderData['customer_email'] ?? $customer->email,
            'customer_phone' => $orderData['customer_phone'] ?? $customer->phone,
            'shipping_address' => $orderData['shipping_address'] ?? '',
            'billing_address' => $orderData['billing_address'] ?? $orderData['shipping_address'] ?? '',
            'total_amount' => $orderData['total_amount'] ?? 0,
            'currency' => $orderData['currency'] ?? 'EUR',
            'order_date' => $orderData['order_date'] ?? now(),
            'additional_data' => $orderData['additional_data'] ?? []
        ]);
        
        Log::info('ACS: Created new order', [
            'order_id' => $order->id,
            'external_order_id' => $externalOrderId
        ]);
        
        return $order;
    }

    /**
     * Find or create courier
     */
    private function findOrCreateCourier($courierName)
    {
        $courier = Courier::where('name', $courierName)->first();
        
        if (!$courier) {
            $courier = Courier::create([
                'name' => $courierName,
                'code' => strtoupper(substr($courierName, 0, 3)),
                'is_active' => true,
                'api_endpoint' => $courierName === 'ACS' ? 'https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest' : null,
            ]);
        }
        
        return $courier;
    }

    /**
     * Create or update shipment
     */
    private function createOrUpdateShipment($order, $shipmentData, $courier)
    {
        $trackingNumber = $shipmentData['tracking_number'];
        
        // Check if order already has a shipment
        $existingShipment = $order->shipments()->where('tracking_number', $trackingNumber)->first();
        
        if ($existingShipment) {
            // Update existing shipment with ACS data
            $existingShipment->update([
                'courier_id' => $courier->id,
                'courier_tracking_id' => $shipmentData['courier_tracking_id'] ?? $trackingNumber,
                'status' => $shipmentData['status'] ?? $existingShipment->status,
                'weight' => $shipmentData['weight'] ?? $existingShipment->weight,
                'shipping_address' => $shipmentData['shipping_address'] ?? $existingShipment->shipping_address,
                'billing_address' => $shipmentData['billing_address'] ?? $existingShipment->billing_address,
                'shipping_cost' => $shipmentData['shipping_cost'] ?? $existingShipment->shipping_cost,
                'courier_response' => $shipmentData['courier_response'] ?? $existingShipment->courier_response,
            ]);
            
            Log::info('ACS: Updated existing shipment', [
                'shipment_id' => $existingShipment->id,
                'order_id' => $order->id,
                'tracking_number' => $trackingNumber,
                'status' => $existingShipment->status
            ]);
            
            return $existingShipment;
        }
        
        // Check if order has any shipment (primary shipment)
        $primaryShipment = $order->primaryShipment;
        
        if ($primaryShipment) {
            // Update the primary shipment with ACS data
            $primaryShipment->update([
                'courier_id' => $courier->id,
                'tracking_number' => $trackingNumber,
                'courier_tracking_id' => $shipmentData['courier_tracking_id'] ?? $trackingNumber,
                'status' => $shipmentData['status'] ?? $primaryShipment->status,
                'weight' => $shipmentData['weight'] ?? $primaryShipment->weight,
                'shipping_address' => $shipmentData['shipping_address'] ?? $primaryShipment->shipping_address,
                'billing_address' => $shipmentData['billing_address'] ?? $primaryShipment->billing_address,
                'shipping_cost' => $shipmentData['shipping_cost'] ?? $primaryShipment->shipping_cost,
                'courier_response' => $shipmentData['courier_response'] ?? $primaryShipment->courier_response,
            ]);
            
            Log::info('ACS: Updated primary shipment with ACS data', [
                'shipment_id' => $primaryShipment->id,
                'order_id' => $order->id,
                'tracking_number' => $trackingNumber
            ]);
            
            return $primaryShipment;
        }

        // Create new shipment for this order
        $shipment = Shipment::create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'courier_id' => $courier->id,
            'tracking_number' => $trackingNumber,
            'courier_tracking_id' => $shipmentData['courier_tracking_id'] ?? $trackingNumber,
            'status' => $shipmentData['status'] ?? 'pending',
            'weight' => $shipmentData['weight'] ?? 0.5,
            'shipping_address' => $shipmentData['shipping_address'] ?? $order->shipping_address,
            'shipping_city' => $shipmentData['shipping_city'] ?? $order->shipping_city,
            'billing_address' => $shipmentData['billing_address'] ?? $order->billing_address,
            'shipping_cost' => $shipmentData['shipping_cost'] ?? 0,
            'courier_response' => $shipmentData['courier_response'] ?? null,
        ]);

        // Link shipment to order as primary shipment
        $order->update(['shipment_id' => $shipment->id]);
        
        // Update order status based on shipment status
        if ($shipmentData['status'] === 'delivered') {
            $order->markAsDelivered();
        } elseif (in_array($shipmentData['status'], ['shipped', 'in_transit'])) {
            $order->markAsShipped($shipment);
        }

        Log::info('ACS: Created new shipment', [
            'shipment_id' => $shipment->id,
            'order_id' => $order->id,
            'tracking_number' => $trackingNumber,
            'status' => $shipment->status
        ]);

        return $shipment;
    }

    /**
     * Create status history from tracking events
     */
    private function createStatusHistory($shipment, $trackingEvents)
    {
        if (empty($trackingEvents)) {
            return;
        }

        foreach ($trackingEvents as $event) {
            // Check if this event already exists
            $existingEvent = ShipmentStatusHistory::where('shipment_id', $shipment->id)
                ->where('happened_at', $event['datetime'])
                ->where('status', $event['action'])
                ->first();

            if (!$existingEvent) {
                ShipmentStatusHistory::create([
                    'shipment_id' => $shipment->id,
                    'status' => $event['action'],
                    'location' => $event['location'] ?? '',
                    'notes' => $event['notes'] ?? '',
                    'happened_at' => $event['datetime'],
                    'source' => 'acs_api'
                ]);
            }
        }
    }
}
