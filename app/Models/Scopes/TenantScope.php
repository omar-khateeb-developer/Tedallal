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
            // في حال عدم وجود مطعم (مثلاً SuperAdmin بلا restaurant_id)
            // نُرجع query لا تعود بأي نتيجة بدلاً من تسريب بيانات مطاعم أخرى.
            // SuperAdmin controllers يجب أن يستخدموا withoutGlobalScope(TenantScope::class).
            $builder->whereRaw('1 = 0');
        }
    }
}
