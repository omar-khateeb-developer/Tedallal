<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;

trait HasTenant
{
    /**
     * Boot the trait to apply the TenantScope automatically.
     */
    protected static function bootHasTenant(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}
