<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\TableSessionResource;
use App\Models\RestaurantTable;
use App\Services\TableSessionService;
use Illuminate\Http\Request;

class SessionController extends ApiController
{
    public function __construct(
        private readonly TableSessionService $tableSessionService,
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'public_token' => ['required', 'string'],
        ]);

        $table = RestaurantTable::query()
            ->where('public_token', $request->string('public_token'))
            ->where('status', 'active')
            ->first();

        if (! $table) {
            return $this->error('الطاولة غير موجودة.', 404);
        }

        $session = $this->tableSessionService->resolveOrCreate($table);
        $session->load('table.restaurant');

        $cookie = cookie(
            'tedallal_session',
            $session->session_token,
            60 * 4,
            '/',
            null,
            app()->isProduction(),
            true,
            false,
            'Lax'
        );

        return $this->success(new TableSessionResource($session))
            ->withCookie($cookie);
    }

    public function show(string $sessionToken)
    {
        $session = $this->tableSessionService->findByToken($sessionToken);

        if (! $session) {
            return $this->error('جلسة غير صالحة.', 404);
        }

        $session->load('table.restaurant');

        return $this->success(new TableSessionResource($session));
    }
}
