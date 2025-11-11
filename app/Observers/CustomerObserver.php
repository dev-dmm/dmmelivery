<?php

namespace App\Observers;

use App\Models\Customer;
use App\Services\GlobalCustomerService;

class CustomerObserver
{
    /**
     * Handle the Customer "updating" event.
     * Re-link to global customer if email or phone changes
     */
    public function updating(Customer $customer): void
    {
        // If email or phone is being changed, re-link to global customer
        if ($customer->isDirty(['email', 'phone'])) {
            $email = trim((string) $customer->email);
            $phone = trim((string) $customer->phone);
            
            // Don't link if both email and phone are empty - clear existing link
            if ($email === '' && $phone === '') {
                $customer->global_customer_id = null;
                return;
            }
            
            $svc = app(GlobalCustomerService::class);
            $globalCustomer = $svc->findOrCreateGlobalCustomer($email, $phone);
            
            // Set the global_customer_id (will be saved with the update)
            $customer->global_customer_id = $globalCustomer->id;
        }
    }
}
