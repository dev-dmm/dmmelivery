<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\AlertRule;
use App\Models\Alert;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class AlertSystemService
{
    /**
     * Check all active shipments for alert conditions
     */
    public function checkAllShipments(): int
    {
        Log::info('🔍 Starting alert system check for all shipments');

        $activeShipments = Shipment::whereNotIn('status', ['delivered', 'cancelled', 'returned'])
            ->where('created_at', '>=', now()->subDays(30))
            ->with(['predictiveEta', 'statusHistory', 'courier', 'customer'])
            ->get();

        $alertsTriggered = 0;

        foreach ($activeShipments as $shipment) {
            $alertsTriggered += $this->checkShipmentAlerts($shipment);
        }

        Log::info("✅ Alert check completed. Triggered {$alertsTriggered} alerts");
        return $alertsTriggered;
    }

    /**
     * Check alerts for a specific shipment
     */
    public function checkShipmentAlerts(Shipment $shipment): int
    {
        $alertsTriggered = 0;
        $activeRules = AlertRule::where('tenant_id', $shipment->tenant_id)
            ->where('is_active', true)
            ->get();

        foreach ($activeRules as $rule) {
            if ($this->shouldTriggerAlert($rule, $shipment)) {
                $this->triggerAlert($rule, $shipment);
                $alertsTriggered++;
            }
        }

        return $alertsTriggered;
    }

    /**
     * Check if an alert rule should trigger
     */
    private function shouldTriggerAlert(AlertRule $rule, Shipment $shipment): bool
    {
        // Check if rule should trigger
        if (!$rule->shouldTrigger($shipment)) {
            return false;
        }

        // Check if we already have an active alert for this rule and shipment
        $existingAlert = Alert::where('alert_rule_id', $rule->id)
            ->where('shipment_id', $shipment->id)
            ->where('status', 'active')
            ->first();

        if ($existingAlert) {
            return false; // Already have an active alert
        }

        return true;
    }

    /**
     * Trigger an alert
     */
    private function triggerAlert(AlertRule $rule, Shipment $shipment): Alert
    {
        Log::info("🚨 Triggering alert: {$rule->name} for shipment {$shipment->tracking_number}");

        // Create alert
        $alert = Alert::create([
            'tenant_id' => $shipment->tenant_id,
            'alert_rule_id' => $rule->id,
            'shipment_id' => $shipment->id,
            'title' => $this->generateAlertTitle($rule, $shipment),
            'description' => $this->generateAlertDescription($rule, $shipment),
            'alert_type' => $rule->alert_type,
            'severity_level' => $rule->severity_level,
            'status' => 'active',
            'triggered_at' => now(),
            'escalation_level' => 0,
            'notification_sent' => false,
            'metadata' => [
                'shipment_status' => $shipment->status,
                'tracking_number' => $shipment->tracking_number,
                'courier_name' => $shipment->courier?->name,
                'customer_name' => $shipment->customer?->name,
                'triggered_at' => now()->toISOString(),
            ],
        ]);

        // Update rule trigger count
        $rule->increment('trigger_count');
        $rule->update(['last_triggered_at' => now()]);

        // Send notifications
        $this->sendAlertNotifications($alert);

        return $alert;
    }

    /**
     * Generate alert title
     */
    private function generateAlertTitle(AlertRule $rule, Shipment $shipment): string
    {
        $shipmentInfo = "Shipment {$shipment->tracking_number}";
        $courierInfo = $shipment->courier ? " ({$shipment->courier->name})" : "";
        
        return match($rule->alert_type) {
            'delay' => "🚨 Delivery Delay Alert - {$shipmentInfo}{$courierInfo}",
            'stuck' => "⚠️ Shipment Stuck Alert - {$shipmentInfo}{$courierInfo}",
            'route_deviation' => "🛣️ Route Deviation Alert - {$shipmentInfo}{$courierInfo}",
            'weather_impact' => "🌧️ Weather Impact Alert - {$shipmentInfo}{$courierInfo}",
            'courier_performance' => "📊 Courier Performance Alert - {$shipmentInfo}{$courierInfo}",
            'predictive_delay' => "🤖 Predictive Delay Alert - {$shipmentInfo}{$courierInfo}",
            default => "Alert: {$rule->name} - {$shipmentInfo}{$courierInfo}"
        };
    }

    /**
     * Generate alert description
     */
    private function generateAlertDescription(AlertRule $rule, Shipment $shipment): string
    {
        $description = $rule->description;
        
        // Add shipment-specific details
        $details = [];
        
        if ($shipment->predictiveEta) {
            $details[] = "Predicted ETA: {$shipment->predictiveEta->predicted_eta}";
            $details[] = "Delay Risk: {$shipment->predictiveEta->delay_risk_level}";
        }
        
        if ($shipment->estimated_delivery) {
            $details[] = "Original ETA: {$shipment->estimated_delivery}";
        }
        
        $details[] = "Current Status: {$shipment->status}";
        $details[] = "Courier: " . ($shipment->courier?->name ?? 'Unknown');
        
        if (!empty($details)) {
            $description .= "\n\nDetails:\n" . implode("\n", $details);
        }
        
        return $description;
    }

    /**
     * Send alert notifications
     */
    private function sendAlertNotifications(Alert $alert): void
    {
        $rule = $alert->alertRule;
        $shipment = $alert->shipment;
        $channels = $rule->notification_channels ?? ['email'];

        foreach ($channels as $channel) {
            $this->sendNotification($alert, $channel);
        }

        $alert->update(['notification_sent' => true]);
    }

    /**
     * Send notification via specific channel
     */
    private function sendNotification(Alert $alert, string $channel): void
    {
        $shipment = $alert->shipment;
        $tenant = $shipment->tenant;

        try {
            switch ($channel) {
                case 'email':
                    $this->sendEmailNotification($alert, $tenant);
                    break;
                    
                case 'sms':
                    $this->sendSmsNotification($alert, $tenant);
                    break;
                    
                case 'slack':
                    $this->sendSlackNotification($alert, $tenant);
                    break;
                    
                case 'webhook':
                    $this->sendWebhookNotification($alert, $tenant);
                    break;
            }
        } catch (\Exception $e) {
            Log::error("Failed to send {$channel} notification for alert {$alert->id}: " . $e->getMessage());
        }
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(Alert $alert, $tenant): void
    {
        $recipients = $this->getNotificationRecipients($tenant);
        
        foreach ($recipients as $recipient) {
            NotificationLog::create([
                'tenant_id' => $alert->tenant_id,
                'shipment_id' => $alert->shipment_id,
                'customer_id' => $alert->shipment->customer_id,
                'type' => 'alert_notification',
                'channel' => 'email',
                'recipient' => $recipient,
                'subject' => $alert->title,
                'message' => $alert->description,
                'status' => 'pending',
                'metadata' => [
                    'alert_id' => $alert->id,
                    'severity' => $alert->severity_level,
                    'alert_type' => $alert->alert_type,
                ],
            ]);
        }
    }

    /**
     * Send SMS notification
     */
    private function sendSmsNotification(Alert $alert, $tenant): void
    {
        $recipients = $this->getNotificationRecipients($tenant, 'phone');
        
        foreach ($recipients as $recipient) {
            NotificationLog::create([
                'tenant_id' => $alert->tenant_id,
                'shipment_id' => $alert->shipment_id,
                'customer_id' => $alert->shipment->customer_id,
                'type' => 'alert_notification',
                'channel' => 'sms',
                'recipient' => $recipient,
                'subject' => null,
                'message' => $alert->title . "\n" . $alert->description,
                'status' => 'pending',
                'metadata' => [
                    'alert_id' => $alert->id,
                    'severity' => $alert->severity_level,
                ],
            ]);
        }
    }

    /**
     * Send Slack notification
     */
    private function sendSlackNotification(Alert $alert, $tenant): void
    {
        // Implementation for Slack notifications
        Log::info("Slack notification for alert {$alert->id} - to be implemented");
    }

    /**
     * Send webhook notification
     */
    private function sendWebhookNotification(Alert $alert, $tenant): void
    {
        if (!$tenant->webhook_url) {
            return;
        }

        // Implementation for webhook notifications
        Log::info("Webhook notification for alert {$alert->id} - to be implemented");
    }

    /**
     * Get notification recipients
     */
    private function getNotificationRecipients($tenant, string $type = 'email'): array
    {
        $recipients = [];
        
        if ($type === 'email') {
            $recipients[] = $tenant->contact_email;
            
            // Add tenant users
            $users = User::where('tenant_id', $tenant->id)->get();
            foreach ($users as $user) {
                if ($user->email) {
                    $recipients[] = $user->email;
                }
            }
        } else {
            $recipients[] = $tenant->contact_phone;
        }
        
        return array_unique($recipients);
    }

    /**
     * Create default alert rules for a tenant
     */
    public function createDefaultAlertRules($tenant): void
    {
        $defaultRules = [
            [
                'name' => 'Shipment Stuck Alert',
                'description' => 'Alert when shipment has been in the same status for more than 24 hours',
                'trigger_conditions' => [
                    ['field' => 'hours_in_current_status', 'operator' => 'greater_than', 'value' => 24]
                ],
                'alert_type' => 'stuck',
                'severity_level' => 'medium',
                'notification_channels' => ['email', 'sms'],
            ],
            [
                'name' => 'Delivery Delay Alert',
                'description' => 'Alert when shipment is delayed by more than 4 hours',
                'trigger_conditions' => [
                    ['field' => 'delay_hours', 'operator' => 'greater_than', 'value' => 4]
                ],
                'alert_type' => 'delay',
                'severity_level' => 'high',
                'notification_channels' => ['email', 'sms'],
            ],
            [
                'name' => 'High Delay Risk Alert',
                'description' => 'Alert when predictive ETA shows high delay risk',
                'trigger_conditions' => [
                    ['field' => 'has_predictive_eta', 'operator' => 'equals', 'value' => true],
                    ['field' => 'delay_risk_level', 'operator' => 'in', 'value' => ['high', 'critical']]
                ],
                'alert_type' => 'predictive_delay',
                'severity_level' => 'high',
                'notification_channels' => ['email'],
            ],
            [
                'name' => 'Low Confidence Prediction Alert',
                'description' => 'Alert when predictive ETA has low confidence',
                'trigger_conditions' => [
                    ['field' => 'has_predictive_eta', 'operator' => 'equals', 'value' => true],
                    ['field' => 'confidence_score', 'operator' => 'less_than', 'value' => 0.5]
                ],
                'alert_type' => 'predictive_delay',
                'severity_level' => 'medium',
                'notification_channels' => ['email'],
            ],
        ];

        foreach ($defaultRules as $ruleData) {
            AlertRule::create(array_merge($ruleData, [
                'tenant_id' => $tenant->id,
                'is_active' => true,
                'trigger_count' => 0,
            ]));
        }
    }
}
