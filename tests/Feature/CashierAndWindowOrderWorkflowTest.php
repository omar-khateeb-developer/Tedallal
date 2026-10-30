<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use App\Services\TableSessionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashierAndWindowOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_cashier_can_only_confirm_payment_and_cannot_advance_kitchen_stages(): void
    {
        // 1. إنشاء مطعم بنظام الدفع المسبق (Prepaid) مع 3 نوافذ
        $restaurant = Restaurant::factory()->create([
            'type' => 'prepaid',
            'windows_count' => 3,
        ]);

        $table = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id]);
        $category = \App\Models\MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'category_id' => $category->id]);

        // 2. مستخدم كاشير ومستخدم شيف على نافذة 2
        $cashier = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
        ]);
        $cashier->assignRole(UserRole::Cashier->value);

        $chef = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
            'default_window' => 2,
        ]);
        $chef->assignRole(UserRole::Chef->value);

        // 3. إنشاء طلب جديد من الزبون (Pending)
        $session = app(TableSessionService::class)->resolveOrCreate($table);
        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-TEST-001',
            'status' => OrderStatus::Pending,
            'service_type' => 'takeaway',
            'subtotal' => '50.00',
            'total' => '50.00',
            'pickup_window' => null,
            'paid_at' => null,
        ]);

        // 4. الشيف يحاول تأكيد الدفع للطلب (يجب أن يُرفض لأن تأكيد الدفع مخصص للكاشير)
        Sanctum::actingAs($chef);
        $chefPaymentAttempt = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::Accepted->value,
        ]);
        $chefPaymentAttempt->assertStatus(422);

        // 4.1 الكاشير لا يمكنه إلغاء الطلب (دوره يقتصر على تأكيد الدفع فقط)
        Sanctum::actingAs($cashier);
        $cashierCancelPending = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::Cancelled->value,
        ]);
        $cashierCancelPending->assertStatus(422);

        // 5. الكاشير يؤكد استلام المبلغ (Pending -> Accepted)
        Sanctum::actingAs($cashier);
        $cashierConfirm = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::Accepted->value,
        ]);
        $cashierConfirm->assertOk();
        $order->refresh();
        $this->assertSame(OrderStatus::Accepted, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame($cashier->id, $order->paid_by);

        // 6. الكاشير يحاول نقل الطلب إلى قيد التحضير (يجب أن يُمنع لأن المطبخ والنوافذ هم المسؤولون)
        $cashierAdvanceAttempt = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::Preparing->value,
            'pickup_window' => 1,
        ]);
        $cashierAdvanceAttempt->assertStatus(422);

        // 7. نافذة المطبخ 2 تستلم الطلب للتحضير (Accepted -> Preparing مع pickup_window = 2)
        Sanctum::actingAs($chef);
        $chefClaim = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::Preparing->value,
            'pickup_window' => 2,
        ]);
        $chefClaim->assertOk();
        $order->refresh();
        $this->assertSame(OrderStatus::Preparing, $order->status);
        $this->assertSame(2, $order->pickup_window);

        // 8. الكاشير يحاول إلغاء الطلب بعد أن دخل مرحلة التحضير بالمطبخ (يجب أن يُمنع، فقط المدير يلغي أثناء التحضير)
        Sanctum::actingAs($cashier);
        $cashierCancelAttempt = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::Cancelled->value,
        ]);
        $cashierCancelAttempt->assertStatus(422);

        // 9. نافذة 2 تعلن الجاهزية (Preparing -> Ready)
        Sanctum::actingAs($chef);
        $chefReady = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::Ready->value,
        ]);
        $chefReady->assertOk();
        $order->refresh();
        $this->assertSame(OrderStatus::Ready, $order->status);
        $this->assertSame(2, $order->pickup_window);

        // 10. تسليم الوجبة للزبون (Ready -> Served)
        $served = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::Served->value,
        ]);
        $served->assertOk();
        $order->refresh();
        $this->assertSame(OrderStatus::Served, $order->status);
    }
}
