<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Shipment;

class WebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Webhook authorization handled by HMAC signature validation.
     */
    public function authorize(): bool
    {
        return true; // HMAC validation happens in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'string', 'max:255'], // Idempotency key
            'tracking_number' => ['required', 'string'],
            'status' => ['required', 'string', Rule::in(Shipment::STATUSES)],
            'description' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:255'],
            'happened_at' => ['nullable', 'date'],
        ];
    }
}

