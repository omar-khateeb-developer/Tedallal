<?php

namespace App\Models\Scopes;

use App\Services\RestaurantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // إذا كنا نعمل في الكونسول (مثل التهجير أو الـ Seeders) أو لم يكن هناك طلب ويب، 
        // أو لا يوجد مطعم نشط محدد، لا تقم بتطبيق الـ Scope.
        if (app()->runningInConsole() || ! app()->has(RestaurantContext::class)) {
            return;
        }

        try {
            $restaurant = app(RestaurantContext::class)->current();
            $builder->where($model->getTable() . '.restaurant_id', $restaurant->id);
        } catch (\RuntimeException $e) {
            // في حال عدم وجود مطعم افتراضي أو أي سبب آخر يمنع إيجاد المطعم،
            // نتجاهل التطبيق لكي لا يتعطل النظام (مثلاً في صفحة المشرف العام)
            // SuperAdmin controller queries will bypass this using withoutGlobalScope(TenantScope::class).
        }
    }
}
