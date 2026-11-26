<?php

namespace App\Services;

use App\Events\TableUpdated;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;

final class TableSessionService
{
    public function __construct(
        private readonly TokenGenerator $tokenGenerator,
    ) {}

    public function resolveOrCreate(RestaurantTable $table, ?string $preferredSessionToken = null): TableSession
    {
        return DB::transaction(function () use ($table, $preferredSessionToken) {
            if ($table->isTakeaway()) {
                // نقاط الاستلام / السفري يمسحها زبائن متفرقون بنفس الـ QR؛
                // لذلك يأخذ كل زبون جلسته المستقلة المربوطة بـ cookie متصفحه فقط.
                $existing = $preferredSessionToken
                    ? TableSession::query()
                        ->where('table_id', $table->id)
                        ->where('session_token', $preferredSessionToken)
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->first()
                    : null;
            } else {
                $existing = TableSession::query()
                    ->where('table_id', $table->id)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                // إذا كانت الطاولة في مرحلة التنظيف بعد مغادرة الضيوف، نغلق أي جلسة قديمة عالقة فوراً حتى لا يرثها الضيف الجديد
                if ($existing && $table->operational_stage === 'cleaning') {
                    $existing->close();
                    $existing = null;
                }
            }

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
                    $existing->setRelation('table', $table);
                    return $existing;
                }
            }

            $newSession = TableSession::query()->create([
                'table_id' => $table->id,
                'session_token' => $this->tokenGenerator->generateSessionToken(),
                'status' => 'active',
                'started_at' => now(),
            ]);

            if (! $table->isTakeaway()) {
                $stage = $table->operational_stage ?: 'available';
                $updates = [
                    'session_started_at' => $table->session_started_at ?? now(),
                    'bill_requested_at' => null,
                ];

                if (in_array($stage, ['available', 'cleaning'], true)) {
                    $updates['operational_stage'] = 'seated';
                    $updates['stage_updated_at'] = now();
                    $updates['current_guest_name'] = $stage === 'cleaning' ? null : $table->current_guest_name;
                }

                $table->update($updates);
                DB::afterCommit(fn () => TableUpdated::dispatchSafe($table));
            }

            $newSession->setRelation('table', $table);

            return $newSession;
        });
    }

    public function findByToken(string $sessionToken): ?TableSession
    {
        return TableSession::query()
            ->where('session_token', $sessionToken)
            ->where('status', 'active')
            ->with(['table' => fn($q) => $q->withoutGlobalScopes(), 'table.restaurant'])
            ->first();
    }

    public function close(TableSession $session): void
    {
        $session->close();
    }
}
