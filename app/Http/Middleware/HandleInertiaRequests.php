<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->roles->first()?->name,
                    'status' => $request->user()->status,
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                ] : null,
                'restaurant' => $request->user() && $request->user()->restaurant ? [
                    'id' => $request->user()->restaurant->id,
                    'name' => $request->user()->restaurant->name,
                    'currency' => $request->user()->restaurant->currency,
                    'logo' => $request->user()->restaurant->logo,
                    'navbar_image' => $request->user()->restaurant->navbar_image,
                ] : null,
            ],
            'restaurantId' => $request->user()?->restaurant_id,
            'locale' => app()->getLocale(),
            'translations' => __('app'),
        ];
    }
}
