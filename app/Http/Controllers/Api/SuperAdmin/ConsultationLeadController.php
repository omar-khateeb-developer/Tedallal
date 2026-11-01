<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\ApiController;
use App\Models\ConsultationLead;
use Illuminate\Http\Request;

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

        return response()->json([
            'success' => true,
            'data' => $leads->items(),
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

        return $this->success($lead->fresh());
    }

    public function destroy(ConsultationLead $lead)
    {
        $lead->delete();

        return $this->success(['message' => 'تم حذف طلب الاستشارة بنجاح']);
    }
}
