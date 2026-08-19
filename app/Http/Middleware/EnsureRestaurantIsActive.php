<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureRestaurantIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && !$user->isSuperAdmin()) {
            if ($user->restaurant && !$user->restaurant->isActive()) {
                Auth::guard('web')->logout();
                
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                if ($request->wantsJson() || $request->is('api/*')) {
                    return response()->json(['message' => 'عذراً، هذا المطعم مُعطّل حالياً من قبل الإدارة.'], 403);
                }

                return redirect()->route('login')->withErrors([
                    'email' => 'عذراً، هذا المطعم مُعطّل حالياً من قبل الإدارة.',
                ]);
            }
        }

        return $next($request);
    }
}
