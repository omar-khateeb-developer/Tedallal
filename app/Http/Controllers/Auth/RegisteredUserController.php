<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\TokenGenerator;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request (provisions a ready-to-use restaurant + admin account).
     *
     * @throws ValidationException
     */
    public function store(Request $request, TokenGenerator $tokenGenerator): RedirectResponse
    {
        $validated = $request->validate([
            'restaurant_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'currency' => ['nullable', 'string', Rule::in(['SYP', 'SAR', 'AED', 'USD', 'JOD', 'IQD'])],
            'type' => ['nullable', 'string', Rule::in(['postpaid', 'prepaid'])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($validated, $tokenGenerator) {
            $restaurantName = trim((string) ($validated['restaurant_name'] ?? ''));
            if ($restaurantName === '') {
                $restaurantName = "مطعم {$validated['name']}";
            }

            $restaurant = Restaurant::create([
                'name' => $restaurantName,
                'slug' => Restaurant::generateUniqueSlug($restaurantName),
                'phone' => $validated['phone'] ?? null,
                'currency' => $validated['currency'] ?? 'SYP',
                'type' => $validated['type'] ?? 'postpaid',
                'plan' => 'basic',
                'status' => 'active',
                'subscription_expires_at' => now()->addDays(14),
            ]);

            RestaurantTable::create([
                'restaurant_id' => $restaurant->id,
                'name' => 'طاولة 1',
                'public_token' => $tokenGenerator->generatePublicToken(),
                'status' => 'active',
                'table_type' => RestaurantTable::TYPE_DINE_IN,
            ]);

            Role::firstOrCreate([
                'name' => UserRole::Admin->value,
                'guard_name' => 'web',
            ]);

            $user = User::create([
                'restaurant_id' => $restaurant->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'status' => UserStatus::Active,
            ]);

            $user->assignRole(UserRole::Admin->value);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
