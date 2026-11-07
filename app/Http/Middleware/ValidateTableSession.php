<?php

namespace App\Http\Middleware;

use App\Models\TableSession;
use App\Services\TableSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateTableSession
{
    public function __construct(
        private readonly TableSessionService $tableSessionService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Try X-Session-Token header first (sent by current active page)
        $headerToken = $request->header('X-Session-Token');
        $session = $headerToken ? $this->tableSessionService->findByToken($headerToken) : null;

        // 2. Fallback to cookie if header is absent or did not resolve
        if (! $session) {
            $cookieToken = $request->cookie('tedallal_session');
            if ($cookieToken) {
                $session = $this->tableSessionService->findByToken($cookieToken);
            }
        }

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'جلسة غير صالحة أو منتهية.',
                'errors' => [],
            ], 401);
        }

        $request->attributes->set('table_session', $session);

        return $next($request);
    }

    public static function sessionFrom(Request $request): TableSession
    {
        /** @var TableSession $session */
        $session = $request->attributes->get('table_session');

        return $session;
    }
}
