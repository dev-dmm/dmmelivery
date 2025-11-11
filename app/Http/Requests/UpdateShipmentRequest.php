<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Shipment;

class UpdateShipmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware + policy
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $shipment = $this->route('shipment');
        $shipmentId = $shipment instanceof Shipment ? $shipment->id : $shipment;

        return [
            'status' => ['nullable', 'string', Rule::in(Shipment::STATUSES)],
            'tracking_number' => ['nullable', 'string', 'max:255', Rule::unique('shipments', 'tracking_number')->ignore($shipmentId)],
            'courier_tracking_id' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'shipping_address' => ['nullable', 'string'],
            'billing_address' => ['nullable', 'string'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'estimated_delivery' => ['nullable', 'date'],
            'actual_delivery' => ['nullable', 'date'],
        ];
    }
}

