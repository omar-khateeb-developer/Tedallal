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
                // فحص انتهاء صلاحية الجلسة القديمة (جلسات الزبائن السابقين):
                // إذا مضى على الجلسة أكثر من 4 ساعات (عمر الكوكي) ولا توجد بها طلبات قيد التحضير/الانتظار،
                // أو إذا مضى عليها أكثر من 12 ساعة بغض النظر، تُعتبر منتهية ويتم إغلاقها لفتح جلسة جديدة نظيفة للزبون الجديد.
                $hasActiveOrders = $existing->orders()
                    ->whereNotIn('status', [\App\Enums\OrderStatus::Served, \App\Enums\OrderStatus::Cancelled])
                    ->exists();

                $isExpiredByTime = $existing->started_at && $existing->started_at->diffInHours(now()) >= 4 && !$hasActiveOrders;
                $isHardExpired = $existing->started_at && $existing->started_at->diffInHours(now()) >= 12;

                if ($isExpiredByTime || $isHardExpired) {
                    $existing->close();
                } else {
                    return $existing;
                }
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
