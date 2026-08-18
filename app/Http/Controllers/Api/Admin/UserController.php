<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\RestaurantContext;
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
            ->where('restaurant_id', $restaurant->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'status', 'permissions', 'created_at']);

        return $this->success($users);
    }

    public function store(Request $request)
    {
        $restaurant = $this->restaurantContext->current();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in([UserRole::Admin->value, UserRole::Staff->value])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'permissions' => $validated['permissions'] ?? [],
            'restaurant_id' => $restaurant->id,
            'password' => Hash::make($validated['password']),
            'status' => UserStatus::Active,
        ]);

        return $this->success($user->only(['id', 'name', 'email', 'role', 'status', 'permissions']), 201);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeUser($user);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
            'role' => ['sometimes', Rule::in([UserRole::Admin->value, UserRole::Staff->value])],
            'status' => ['sometimes', Rule::in([UserStatus::Active->value, UserStatus::Inactive->value])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return $this->success($user->fresh()->only(['id', 'name', 'email', 'role', 'status', 'permissions']));
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
            ->where('restaurant_id', $this->restaurantContext->current()->id)
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
