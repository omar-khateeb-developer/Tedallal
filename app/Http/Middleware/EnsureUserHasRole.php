<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActive()) {
            abort(403, 'حسابك معطل أو غير نشط.');
        }

        if (! $user->hasAnyRole($roles)) {
            abort(403, $this->buildUnauthorizedMessage($roles));
        }

        return $next($request);
    }

    /**
     * @param  array<int, string>  $roles
     */
    protected function buildUnauthorizedMessage(array $roles): string
    {
        if (count($roles) === 1) {
            return match ($roles[0]) {
                UserRole::Admin->value => 'عذراً، هذه الصلاحية خاصة بمدير المطعم (admin) فقط.',
                UserRole::SuperAdmin->value => 'عذراً، هذه الصلاحية خاصة بمدير النظام (super_admin) فقط.',
                default => 'عذراً، هذه الصلاحية مخصصة لرتبة: '.UserRole::labelFor($roles[0]).' فقط.',
            };
        }

        $labels = array_map(fn (string $role): string => UserRole::labelFor($role), $roles);

        return 'عذراً، هذه الصلاحية مخصصة لـ: '.implode(' أو ', $labels).' فقط.';
    }
}
