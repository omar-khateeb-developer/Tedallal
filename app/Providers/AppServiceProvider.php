<?php

namespace App\Providers;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Policies\MenuCategoryPolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\OrderPolicy;
use App\Policies\RestaurantPolicy;
use App\Policies\RestaurantTablePolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Restaurant::class => RestaurantPolicy::class,
        RestaurantTable::class => RestaurantTablePolicy::class,
        MenuCategory::class => MenuCategoryPolicy::class,
        MenuItem::class => MenuItemPolicy::class,
        Order::class => OrderPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->configureRateLimiting();

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin() || $user->isAdmin()) {
                return true;
            }
            return null;
        });
    }

    /**
     * تكوين محددات معدل الطلبات (Rate Limiters) الذكية لحماية الشبكات المشتركة (Wi-Fi Aware)
     */
    protected function configureRateLimiting(): void
    {
        // 1. التصفح العام لقوائم الطعام وتفاصيل الطاولات
        RateLimiter::for('public-api', function (Request $request) {
            $session = $request->cookie('tedallal_session') 
                ?? $request->header('X-Session-Token');

            if ($session) {
                return Limit::perMinute(120)->by('public-api:session:' . $session);
            }

            // لشبكة Wi-Fi المشتركة بدون جلسة، يتم التوسيع لـ 300 طلب بالدقيقة لمنع حظر الزوار
            return Limit::perMinute(300)->by('public-api:ip:' . $request->ip());
        });

        // 2. تأكيد وإرسال الطلبات (معزول لكل جلسة طاولة على حدة)
        RateLimiter::for('public-orders', function (Request $request) {
            $session = $request->cookie('tedallal_session') 
                ?? $request->header('X-Session-Token')
                ?? $request->input('session_token');

            $key = $session ? 'order:session:' . $session : 'order:ip:' . $request->ip();

            return Limit::perMinute(15)->by($key)->response(function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => 'تم تجاوز الحد المسموح من الطلبات لهذه الطاولة، يرجى الانتظار دقيقة والمحاولة مجدداً.',
                    'errors' => [
                        'order' => ['يرجى الانتظار دقيقة قبل إرسال طلب جديد.'],
                    ],
                ], 429, $headers);
            });
        });

        // 3. فحص وتطبيق كوبونات الخصم (معزول لكل طاولة لمنع التخمين)
        RateLimiter::for('public-promo-codes', function (Request $request) {
            $session = $request->cookie('tedallal_session') 
                ?? $request->header('X-Session-Token');

            $key = $session ? 'promo:session:' . $session : 'promo:ip:' . $request->ip();

            return Limit::perMinute(15)->by($key)->response(function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => 'تم تجاوز عدد محاولات فحص الكوبونات لهذه الطاولة، يرجى المحاولة بعد دقيقة.',
                    'errors' => [
                        'promo_code' => ['تم تجاوز عدد محاولات إدخال الكوبون المسموح بها.'],
                    ],
                ], 429, $headers);
            });
        });

        // 4. بدء جلسات الطاولات (معزول لرمز كل طاولة على حدة)
        RateLimiter::for('public-sessions', function (Request $request) {
            $tableToken = $request->input('table_token') ?? 'generic';
            $key = 'session:' . $request->ip() . ':' . $tableToken;

            return Limit::perMinute(20)->by($key)->response(function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => 'يرجى الانتظار لحظات قبل بدء جلسة جديدة لهذه الطاولة.',
                ], 429, $headers);
            });
        });
    }
}
