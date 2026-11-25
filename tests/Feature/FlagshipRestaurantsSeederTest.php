<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderWheelToken;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\RestaurantCustomer;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use App\Models\TableServiceCall;
use App\Models\User;
use App\Models\WaitlistEntry;
use Database\Seeders\FlagshipRestaurantsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FlagshipRestaurantsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_populates_both_flagships_idempotently_with_complete_operations(): void
    {
        Storage::fake('public');

        $this->seed(FlagshipRestaurantsSeeder::class);
        $this->seed(FlagshipRestaurantsSeeder::class);

        $this->assertSame(2, Restaurant::query()->whereIn('slug', ['jamra-craft', 'dar-alyasmin'])->count());

        // 1. مطعم جمرة آند كرافت (Prepaid — Max — 3 نوافذ — كل الميزات)
        $jamra = Restaurant::query()->where('slug', 'jamra-craft')->firstOrFail();
        $this->assertSame('prepaid', $jamra->type);
        $this->assertSame(3, $jamra->windows_count);
        $this->assertTrue($jamra->isMax());
        $this->assertTrue($jamra->hasFloorPlanFeature());
        $this->assertTrue($jamra->hasOffersFeature());
        $this->assertTrue($jamra->hasAnalyticsFeature());
        $this->assertTrue($jamra->hasAiWaiterFeature());
        $this->assertTrue($jamra->hasMenuEngineeringFeature());
        $this->assertTrue($jamra->hasWhatsappAiReplyFeature());
        $this->assertTrue($jamra->loyaltyConfig()['enabled']);
        $this->assertSame('free_item', $jamra->loyaltyConfig()['reward_type']);

        $this->assertSame(8, User::query()->where('restaurant_id', $jamra->id)->count());
        $this->assertSame(5, MenuCategory::withoutGlobalScopes()->where('restaurant_id', $jamra->id)->count());
        $this->assertSame(20, MenuItem::withoutGlobalScopes()->where('restaurant_id', $jamra->id)->count());
        $this->assertSame(10, RestaurantTable::withoutGlobalScopes()->where('restaurant_id', $jamra->id)->count());
        $this->assertSame(5, PromoCode::withoutGlobalScopes()->where('restaurant_id', $jamra->id)->count());
        $this->assertSame(6, RestaurantCustomer::withoutGlobalScopes()->where('restaurant_id', $jamra->id)->count());
        $this->assertSame(15, Order::withoutGlobalScopes()->where('restaurant_id', $jamra->id)->count());
        $this->assertSame(3, OrderWheelToken::query()->where('restaurant_id', $jamra->id)->count());
        $this->assertSame(3, WaitlistEntry::query()->where('restaurant_id', $jamra->id)->count());
        $this->assertSame(2, TableReservation::query()->where('restaurant_id', $jamra->id)->count());

        // 2. مطعم دار الياسمين الدمشقي (Postpaid لاحق الدفع — Max — الحجز المسبق VIP)
        $yasmin = Restaurant::query()->where('slug', 'dar-alyasmin')->firstOrFail();
        $this->assertSame('postpaid', $yasmin->type);
        $this->assertTrue($yasmin->isPostpaid());
        $this->assertTrue($yasmin->isMax());
        $this->assertTrue($yasmin->hasFloorPlanFeature());
        $this->assertTrue($yasmin->loyaltyConfig()['enabled']);
        $this->assertSame('percentage', $yasmin->loyaltyConfig()['reward_type']);

        $this->assertSame(6, User::query()->where('restaurant_id', $yasmin->id)->count());
        $this->assertSame(5, MenuCategory::withoutGlobalScopes()->where('restaurant_id', $yasmin->id)->count());
        $this->assertSame(19, MenuItem::withoutGlobalScopes()->where('restaurant_id', $yasmin->id)->count());
        $this->assertSame(11, RestaurantTable::withoutGlobalScopes()->where('restaurant_id', $yasmin->id)->count());
        $this->assertSame(6, TableReservation::query()->where('restaurant_id', $yasmin->id)->count());
        $this->assertSame(4, WaitlistEntry::query()->where('restaurant_id', $yasmin->id)->count());
        $this->assertSame(3, TableServiceCall::withoutGlobalScopes()->where('restaurant_id', $yasmin->id)->count());
        $this->assertSame(15, Order::withoutGlobalScopes()->where('restaurant_id', $yasmin->id)->count());

        // التأكد من عدم استخدام الصورة الافتراضية لأي صنف وتوافر ملفات الصور فعلياً
        $allItems = MenuItem::withoutGlobalScopes()->whereIn('restaurant_id', [$jamra->id, $yasmin->id])->get();
        foreach ($allItems as $item) {
            $this->assertNotSame(MenuItem::DEFAULT_IMAGE, $item->image, "Item {$item->name} fell back to DEFAULT_IMAGE");
            $this->assertFileExists(public_path(ltrim($item->image, '/')));
        }
        foreach ([$jamra, $yasmin] as $r) {
            $this->assertFileExists(public_path(ltrim($r->logo, '/')));
            $this->assertFileExists(public_path(ltrim($r->navbar_image, '/')));
        }

        // التحقق من توفر القوائم وصفحة الحجز المسبق VIP والطابور وعجلة الحظ والأدمن
        $this->get('/m/jamra-craft')->assertOk();
        $this->get('/m/dar-alyasmin')->assertOk();
        $this->get('/r/dar-alyasmin/vip-booking')->assertOk();
        $this->get('/r/dar-alyasmin/waitlist')->assertOk();
        $this->get('/wheel/JMR-WHEEL-FREE-01')->assertOk();
        $this->get('/wheel/YSM-WHEEL-VIP-01')->assertOk();

        // التحقق من مسار الكاشير و API تحليلات مطعم جمرة ومخطط طاولات دار الياسمين
        $jamraAdmin = User::where('email', 'admin@jamra.test')->firstOrFail();
        $this->actingAs($jamraAdmin)->get('/admin/dashboard')->assertOk();
        $this->actingAs($jamraAdmin)->getJson('/api/admin/analytics?period=all')->assertOk()->assertJsonPath('success', true);

        $yasminAdmin = User::where('email', 'admin@yasmin.test')->firstOrFail();
        $this->actingAs($yasminAdmin)->get('/admin/floor-plan')->assertOk();
        $this->actingAs($yasminAdmin)->getJson('/api/admin/reservations')->assertOk()->assertJsonPath('success', true);
    }
}
