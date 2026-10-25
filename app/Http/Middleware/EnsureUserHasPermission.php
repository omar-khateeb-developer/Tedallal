<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActive()) {
            abort(403, 'حسابك معطل أو غير نشط.');
        }

        if (! $user->hasPermission($permission)) {
            abort(403, 'ليس لديك الصلاحية للوصول إلى هذا المورد.');
        }

        return $next($request);
    }
}
