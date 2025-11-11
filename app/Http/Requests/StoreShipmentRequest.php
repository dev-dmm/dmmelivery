<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Shipment;

class StoreShipmentRequest extends FormRequest
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
        return [
            'order_id' => ['required', 'exists:orders,id'],
            'courier_id' => ['nullable', 'exists:couriers,id'],
            'tracking_number' => ['nullable', 'string', 'max:255', 'unique:shipments,tracking_number'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'shipping_address' => ['required', 'string'],
            'billing_address' => ['nullable', 'string'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'estimated_delivery' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * Prepare the data for validation.
     * Normalize timezone for estimated_delivery if provided.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('estimated_delivery') && $this->estimated_delivery) {
            // Ensure timezone is set (default to app timezone)
            try {
                $date = \Carbon\Carbon::parse($this->estimated_delivery, config('app.timezone'));
                $this->merge([
                    'estimated_delivery' => $date->toDateTimeString(),
                ]);
            } catch (\Exception $e) {
                // Invalid date format, let validation handle it
            }
        }
    }
}

