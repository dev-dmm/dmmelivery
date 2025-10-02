<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (app()->has('tenant')) {
            $builder->where('tenant_id', app('tenant')->id);
        }
    }

    public function extend(Builder $builder)
    {
        $this->addWithoutTenantScope($builder);
    }

    protected function addWithoutTenantScope(Builder $builder)
    {
        $builder->macro('withoutTenantScope', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}