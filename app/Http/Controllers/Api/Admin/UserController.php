<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\RestaurantContext;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
    ) {}

    public function index()
    {
        $restaurant = $this->restaurantContext->current();

        $users = User::query()
            ->with(['roles', 'permissions'])
            ->where('restaurant_id', $restaurant->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status', 'created_at']);

        $users->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'role' => $user->roles->first()?->name,
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'created_at' => $user->created_at,
            ];
        });

        return $this->success($users);
    }

    public function store(StoreAdminUserRequest $request)
    {
        $restaurant = $this->restaurantContext->current();
        $validated = $request->validated();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'restaurant_id' => $restaurant->id,
            'password' => Hash::make($validated['password']),
            'status' => \App\Enums\UserStatus::Active,
        ]);

        $user->assignRole($validated['role']);
        if (!empty($validated['permissions'])) {
            $user->syncPermissions($validated['permissions']);
        }

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'role' => $user->roles->first()?->name,
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ], 201);
    }

    public function update(UpdateAdminUserRequest $request, User $user)
    {
        $this->authorizeUser($user);
        $validated = $request->validated();

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

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'role' => $user->roles->first()?->name,
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizeUser($user);

        if ($user->id === $request->user()->id) {
            return $this->error('لا يمكنك حذف حسابك.', 422);
        }

        $user->delete();

        return response()->json(['success' => true, 'data' => null]);
    }

    public function logs(User $user)
    {
        $this->authorizeUser($user);

        $logs = \App\Models\ActivityLog::query()
            ->where('user_id', $user->id)
            ->latest()
            ->take(50)
            ->get();

        return $this->success($logs);
    }

    private function authorizeUser(User $user): void
    {
        if ($user->restaurant_id !== $this->restaurantContext->current()->id) {
            abort(404);
        }
    }
}
