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
        $token = $request->cookie('tedallal_session')
            ?? $request->header('X-Session-Token');

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'جلسة غير صالحة.',
                'errors' => [],
            ], 401);
        }

        $session = $this->tableSessionService->findByToken($token);

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
