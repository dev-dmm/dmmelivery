<?php

namespace App\Support;

use App\Models\Scopes\TenantScope;

trait NoTenant
{
    protected function noTenant($query)
    {
        return $query->withoutGlobalScopes([TenantScope::class]);
    }
}
