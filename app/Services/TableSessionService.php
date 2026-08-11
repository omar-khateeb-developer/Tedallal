<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;

final class TableSessionService
{
    public function __construct(
        private readonly TokenGenerator $tokenGenerator,
    ) {}

    public function resolveOrCreate(RestaurantTable $table): TableSession
    {
        return DB::transaction(function () use ($table) {
            $existing = TableSession::query()
                ->where('table_id', $table->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            return TableSession::query()->create([
                'table_id' => $table->id,
                'session_token' => $this->tokenGenerator->generateSessionToken(),
                'status' => 'active',
                'started_at' => now(),
            ]);
        });
    }

    public function findByToken(string $sessionToken): ?TableSession
    {
        return TableSession::query()
            ->where('session_token', $sessionToken)
            ->where('status', 'active')
            ->first();
    }

    public function close(TableSession $session): void
    {
        $session->close();
    }
}
