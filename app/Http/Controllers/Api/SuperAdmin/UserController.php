<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends ApiController
{
    public function index()
    {
        $users = User::query()
            ->with(['restaurant:id,name', 'roles', 'permissions'])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'email', 'status', 'created_at', 'restaurant_id']);

        $users->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'role' => $user->roles->first()?->name,
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'created_at' => $user->created_at,
                'restaurant' => $user->restaurant,
                'restaurant_id' => $user->restaurant_id,
            ];
        });

        return $this->success($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in([UserRole::SuperAdmin->value, UserRole::Admin->value, UserRole::Staff->value])],
            'restaurant_id' => ['nullable', 'exists:restaurants,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        if ($validated['role'] === UserRole::SuperAdmin->value) {
            $validated['restaurant_id'] = null; // Superadmins don't belong to a restaurant
        } elseif (empty($validated['restaurant_id'])) {
            return $this->error('يجب تحديد المطعم لهذا الدور', 422);
        }

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'restaurant_id' => $validated['restaurant_id'],
            'password' => Hash::make($validated['password']),
            'status' => \App\Enums\UserStatus::Active,
        ]);

        $user->assignRole($validated['role']);
        if (!empty($validated['permissions'])) {
            $user->syncPermissions($validated['permissions']);
        }

        $user->load('restaurant:id,name');

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'role' => $user->roles->first()?->name,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'restaurant' => $user->restaurant,
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
            'role' => ['sometimes', Rule::in([UserRole::SuperAdmin->value, UserRole::Admin->value, UserRole::Staff->value])],
            'restaurant_id' => ['nullable', 'exists:restaurants,id'],
            'status' => ['sometimes', Rule::in([UserStatus::Active->value, UserStatus::Inactive->value])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        if (isset($validated['role'])) {
            if ($validated['role'] === UserRole::SuperAdmin->value) {
                $validated['restaurant_id'] = null;
            } elseif (empty($validated['restaurant_id']) && empty($user->restaurant_id)) {
                if (isset($validated['restaurant_id'])) {
                    // if they explicitly set it to null
                    return $this->error('يجب تحديد المطعم لهذا الدور', 422);
                } else if ($user->role === UserRole::SuperAdmin->value) {
                     // changing from superadmin to admin without providing restaurant
                     return $this->error('يجب تحديد المطعم لهذا الدور', 422);
                }
            }
        }

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        if (array_key_exists('permissions', $validated)) {
            $user->syncPermissions($validated['permissions'] ?? []);
        }

        $user->load('restaurant:id,name');

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'role' => $user->roles->first()?->name,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'restaurant' => $user->restaurant,
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->email === 'super@tedallal.test') {
            return $this->error('لا يمكن حذف حساب المالك الرئيسي للمنصة.', 403);
        }

        if ($user->id === $request->user()->id) {
            return $this->error('لا يمكنك حذف حسابك.', 422);
        }

        $user->delete();

        return response()->json(['success' => true, 'data' => null]);
    }

    public function logs(User $user)
    {
        $logs = \App\Models\ActivityLog::query()
            ->where('user_id', $user->id)
            ->latest()
            ->take(50)
            ->get();

        return $this->success($logs);
    }
}
