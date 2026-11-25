<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\RestaurantPlan;
use App\Enums\RestaurantStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\RestaurantCustomer;
use App\Models\RestaurantTable;
use App\Services\BackupService;
use App\Services\TableSessionService;
use App\Services\WhatsAppGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Sleep;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_digest_uses_recognized_revenue_whatsapp_fallback_and_real_prep_metrics(): void
    {
        $restaurant = Restaurant::factory()->create([
            'name' => 'مطعم الشام الأصيل',
            'status' => RestaurantStatus::Active,
            'currency' => 'SYP',
            'phone' => null,
            'whatsapp_phone' => '+963991112233',
            'whatsapp_status' => 'connected',
        ]);

        $table = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id]);
        $session = app(TableSessionService::class)->resolveOrCreate($table);
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'شاورما عربي',
            'price' => '50.00',
        ]);

        // Served order with 15-minute prep duration
        $servedOrder = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'DIG-101',
            'status' => OrderStatus::Served,
            'subtotal' => '150.50',
            'discount_amount' => '0.00',
            'total' => '150.50',
            'ready_at' => now()->subMinutes(5),
        ]);
        $servedOrder->forceFill(['created_at' => now()->subMinutes(20)])->saveQuietly();
        $servedOrder->items()->create([
            'menu_item_id' => $item->id,
            'item_name_snapshot' => $item->name,
            'unit_price' => '50.00',
            'quantity' => 3,
            'subtotal' => '150.50',
        ]);

        // Prepaid non-served order (counts as recognized revenue)
        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'DIG-102',
            'status' => OrderStatus::Preparing,
            'paid_at' => now(),
            'subtotal' => '49.50',
            'discount_amount' => '0.00',
            'total' => '49.50',
            'created_at' => now()->subMinutes(10),
        ]);

        // Unpaid pending order (must NOT count in revenue)
        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'DIG-103',
            'status' => OrderStatus::Pending,
            'subtotal' => '999.00',
            'discount_amount' => '0.00',
            'total' => '999.00',
        ]);

        // Cancelled paid order (must NOT count in revenue, counts toward cancelled count)
        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'DIG-104',
            'status' => OrderStatus::Cancelled,
            'paid_at' => now(),
            'subtotal' => '500.00',
            'discount_amount' => '0.00',
            'total' => '500.00',
        ]);

        $gateway = Mockery::mock(WhatsAppGatewayService::class);
        $gateway->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function (Restaurant $target, string $phone, string $message, bool $simulateTyping) use ($restaurant) {
                return $target->id === $restaurant->id
                    && $phone === '+963991112233'
                    && $simulateTyping === false
                    && str_contains($message, '200 ل.س (2 فاتورة)')
                    && str_contains($message, 'شاورما عربي (3 طلب)')
                    && str_contains($message, '15 دقيقة')
                    && str_contains($message, '1 فواتير ملغاة');
            })
            ->andReturn(['success' => true, 'message_id' => 'wa-digest-1']);
        $this->app->instance(WhatsAppGatewayService::class, $gateway);

        $this->artisan('tedallal:send-daily-digest', ['--restaurant' => $restaurant->id])
            ->assertExitCode(0);
    }

    public function test_daily_digest_avoids_synthetic_prep_time_and_preserves_decimals(): void
    {
        $restaurant = Restaurant::factory()->create([
            'status' => RestaurantStatus::Active,
            'currency' => 'USD',
            'phone' => '0999888777',
            'whatsapp_status' => 'disconnected',
        ]);

        $table = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id]);
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'DIG-USD-1',
            'status' => OrderStatus::Served,
            'subtotal' => '12.50',
            'discount_amount' => '0.00',
            'total' => '12.50',
            'ready_at' => null,
        ]);

        $gateway = Mockery::mock(WhatsAppGatewayService::class);
        $gateway->shouldReceive('sendPlatformMessage')
            ->once()
            ->withArgs(function (string $phone, string $message, bool $simulateTyping) {
                return $phone === '0999888777'
                    && $simulateTyping === false
                    && str_contains($message, '12.50 USD (1 فاتورة)')
                    && str_contains($message, 'لا توجد بيانات كافية')
                    && ! str_contains($message, '12 دقيقة');
            })
            ->andReturn(['success' => true]);
        $this->app->instance(WhatsAppGatewayService::class, $gateway);

        $this->artisan('tedallal:send-daily-digest', ['--restaurant' => $restaurant->id])
            ->assertExitCode(0);
    }

    public function test_expiring_voucher_reminders_respect_feature_window_and_state(): void
    {
        Sleep::fake();

        $eligibleRestaurant = Restaurant::factory()->create([
            'status' => RestaurantStatus::Active,
            'plan' => RestaurantPlan::Max,
            'features' => ['whatsapp_reminders' => true],
            'whatsapp_status' => 'connected',
            'whatsapp_expiring_reminders_enabled' => true,
        ]);

        $disabledRestaurant = Restaurant::factory()->create([
            'status' => RestaurantStatus::Active,
            'plan' => RestaurantPlan::Max,
            'features' => ['whatsapp_reminders' => true],
            'whatsapp_status' => 'connected',
            'whatsapp_expiring_reminders_enabled' => false,
        ]);

        $eligibleCustomer = RestaurantCustomer::create([
            'restaurant_id' => $eligibleRestaurant->id,
            'name' => 'أحمد العمري',
            'phone' => '0933445566',
            'visits_count' => 2,
        ]);

        $disabledCustomer = RestaurantCustomer::create([
            'restaurant_id' => $disabledRestaurant->id,
            'name' => 'ليلى سالم',
            'phone' => '0944556677',
            'visits_count' => 1,
        ]);

        $eligiblePromo = PromoCode::create([
            'restaurant_id' => $eligibleRestaurant->id,
            'customer_id' => $eligibleCustomer->id,
            'code' => 'EXP-ELIGIBLE',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'is_active' => true,
            'times_used' => 0,
            'is_expiring_reminded' => false,
            'expires_at' => now()->addHours(24),
        ]);

        // Already used -> skipped
        PromoCode::create([
            'restaurant_id' => $eligibleRestaurant->id,
            'customer_id' => $eligibleCustomer->id,
            'code' => 'EXP-USED',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'is_active' => true,
            'times_used' => 1,
            'is_expiring_reminded' => false,
            'expires_at' => now()->addHours(24),
        ]);

        // Outside 18-36h window -> skipped
        $outsideWindowPromo = PromoCode::create([
            'restaurant_id' => $eligibleRestaurant->id,
            'customer_id' => $eligibleCustomer->id,
            'code' => 'EXP-LATER',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
            'times_used' => 0,
            'is_expiring_reminded' => false,
            'expires_at' => now()->addHours(72),
        ]);

        // Restaurant reminders toggle disabled -> skipped
        $disabledTogglePromo = PromoCode::create([
            'restaurant_id' => $disabledRestaurant->id,
            'customer_id' => $disabledCustomer->id,
            'code' => 'EXP-OFF',
            'discount_type' => 'percentage',
            'discount_value' => 25,
            'is_active' => true,
            'times_used' => 0,
            'is_expiring_reminded' => false,
            'expires_at' => now()->addHours(24),
        ]);

        $gateway = Mockery::mock(WhatsAppGatewayService::class);
        $gateway->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function (Restaurant $target, string $phone, string $message) use ($eligibleRestaurant) {
                return $target->id === $eligibleRestaurant->id
                    && $phone === '0933445566'
                    && str_contains($message, 'EXP-ELIGIBLE');
            })
            ->andReturn(['success' => true]);
        $this->app->instance(WhatsAppGatewayService::class, $gateway);

        $this->artisan('crm:send-expiring-reminders')
            ->assertExitCode(0);

        $this->assertTrue($eligiblePromo->fresh()->is_expiring_reminded);
        $this->assertFalse($outsideWindowPromo->fresh()->is_expiring_reminded);
        $this->assertFalse($disabledTogglePromo->fresh()->is_expiring_reminded);
    }

    public function test_audit_system_detects_and_repairs_broken_media_and_missing_qr(): void
    {
        Storage::fake('public');

        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $brokenItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'image' => 'uploads/non-existent-dish.jpg',
        ]);
        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
            'public_token' => 'AUDITTOK01',
        ]);

        $this->artisan('tedallal:audit-system')
            ->assertExitCode(1);

        $this->assertSame('uploads/non-existent-dish.jpg', $brokenItem->fresh()->getRawOriginal('image'));
        Storage::disk('public')->assertMissing("qr/{$restaurant->id}/AUDITTOK01.png");

        $this->artisan('tedallal:audit-system', ['--fix' => true])
            ->assertExitCode(0);

        $this->assertNull($brokenItem->fresh()->getRawOriginal('image'));
        Storage::disk('public')->assertExists("qr/{$restaurant->id}/AUDITTOK01.png");
    }

    public function test_backup_command_forwards_no_media_flag_and_handles_failures(): void
    {
        $backupService = Mockery::mock(BackupService::class);
        $backupService->shouldReceive('createBackup')
            ->once()
            ->with(false)
            ->andReturn([
                'filename' => 'tedallal_backup_test.zip',
                'size_formatted' => '1.25 MB',
                'tables_count' => 18,
                'rows_count' => 420,
                'media_files_count' => 0,
                'duration_seconds' => 0.42,
                'path' => '/tmp/tedallal_backup_test.zip',
            ]);
        $this->app->instance(BackupService::class, $backupService);

        $this->artisan('tedallal:backup', ['--no-media' => true])
            ->expectsOutputToContain('tedallal_backup_test.zip')
            ->assertExitCode(0);

        $failingService = Mockery::mock(BackupService::class);
        $failingService->shouldReceive('createBackup')
            ->once()
            ->with(true)
            ->andThrow(new \RuntimeException('Simulated storage error'));
        $this->app->instance(BackupService::class, $failingService);

        $this->artisan('tedallal:backup')
            ->assertExitCode(1);
    }
}
