<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AlertRule;
use App\Models\Tenant;

class AlertRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->createDefaultAlertRules($tenant);
        }
    }

    private function createDefaultAlertRules(Tenant $tenant): void
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
                'is_active' => true,
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
                'is_active' => true,
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
                'is_active' => true,
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
                'is_active' => true,
            ],
            [
                'name' => 'Critical Delay Alert',
                'description' => 'Alert when shipment is delayed by more than 24 hours',
                'trigger_conditions' => [
                    ['field' => 'delay_hours', 'operator' => 'greater_than', 'value' => 24]
                ],
                'alert_type' => 'delay',
                'severity_level' => 'critical',
                'notification_channels' => ['email', 'sms'],
                'is_active' => true,
            ],
        ];

        foreach ($defaultRules as $ruleData) {
            AlertRule::create(array_merge($ruleData, [
                'tenant_id' => $tenant->id,
                'trigger_count' => 0,
            ]));
        }
    }
}
