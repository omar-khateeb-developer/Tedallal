<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\ApiController;
use App\Models\ConsultationLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ConsultationLeadController extends ApiController
{
    public function index(Request $request)
    {
        $query = ConsultationLead::query()->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('restaurant_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        $leads = $query->paginate($request->integer('per_page', 25));

        $stats = [
            'total' => ConsultationLead::count(),
            'new' => ConsultationLead::where('status', 'new')->count(),
            'contacted' => ConsultationLead::where('status', 'contacted')->count(),
            'qualified' => ConsultationLead::where('status', 'qualified')->count(),
            'closed_won' => ConsultationLead::where('status', 'closed_won')->count(),
            'closed_lost' => ConsultationLead::where('status', 'closed_lost')->count(),
        ];

        $items = collect($leads->items())->map(function ($lead) {
            $arr = $lead->toArray();
            $arr['ai_paused'] = Cache::has("platform_wa_paused_{$lead->phone}");
            return $arr;
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'total' => $leads->total(),
            ],
            'stats' => $stats,
        ]);
    }

    public function update(Request $request, ConsultationLead $lead)
    {
        $validated = $request->validate([
            'status' => 'sometimes|required|string|in:new,contacted,qualified,closed_won,closed_lost',
            'notes' => 'sometimes|nullable|string|max:5000',
        ]);

        $lead->update($validated);

        $res = $lead->fresh()->toArray();
        $res['ai_paused'] = Cache::has("platform_wa_paused_{$lead->phone}");

        return $this->success($res);
    }

    /**
     * تفعيل أو إيقاف المساعد الذكي لمحادثة هذا العميل (Human Takeover Toggle)
     */
    public function toggleAi(ConsultationLead $lead): JsonResponse
    {
        $key = "platform_wa_paused_{$lead->phone}";
        if (Cache::has($key)) {
            Cache::forget($key);
            $paused = false;
        } else {
            Cache::put($key, true, now()->addHours(24));
            $paused = true;
        }

        return $this->success([
            'ai_paused' => $paused,
            'message' => $paused
                ? 'تم إيقاف المساعد الذكي لهذا العميل مؤقتاً لتتمكن من التحدث معه بحرية.'
                : 'تمت إعادة تفويض المساعد الذكي للرد على هذا العميل تلقائياً.',
        ]);
    }

    public function destroy(ConsultationLead $lead)
    {
        $lead->delete();

        return $this->success(['message' => 'تم حذف طلب الاستشارة بنجاح']);
    }
}

