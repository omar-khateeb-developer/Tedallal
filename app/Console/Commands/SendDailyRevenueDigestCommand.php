<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\RestaurantCustomer;
use App\Services\WhatsAppGatewayService;
use App\Support\MoneyCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyRevenueDigestCommand extends Command
{
    protected $signature = 'tedallal:send-daily-digest {--restaurant= : Specific restaurant ID} {--phone= : Override recipient phone} {--test : Send simulated test digest if no orders}';

    protected $description = 'Send midnight revenue, KDS speed, and cash-reconciliation digest to restaurant owners via WhatsApp';

    public function handle(WhatsAppGatewayService $gatewayService): int
    {
        $this->info('Starting Tedallal Midnight Revenue WhatsApp Digest...');

        $query = Restaurant::query()->where('status', 'active');
        if ($restaurantId = $this->option('restaurant')) {
            $query->where('id', $restaurantId);
        }

        $restaurants = $query->get();
        $this->info("Found {$restaurants->count()} active restaurants to evaluate.");

        $sentCount = 0;

        foreach ($restaurants as $restaurant) {
            $phone = $this->option('phone') ?: ($restaurant->phone ?: $restaurant->whatsapp_phone);

            if (! $phone) {
                $this->warn("Skipping restaurant {$restaurant->name} (ID: {$restaurant->id}): No contact phone found.");
                continue;
            }

            $todayOrders = Order::withoutGlobalScopes()
                ->where('restaurant_id', $restaurant->id)
                ->where('created_at', '>=', now()->startOfDay());

            // القاعدة المحاسبية الموحدة عبر النطاق المعياري المعتمد
            $completedOrders = (clone $todayOrders)
                ->recognizedRevenue()
                ->get(['id', 'total', 'created_at', 'ready_at']);

            $totalSalesStr = '0.00';
            foreach ($completedOrders as $o) {
                $totalSalesStr = MoneyCalculator::add($totalSalesStr, MoneyCalculator::normalize((string) $o->total));
            }
            $ordersCount = $completedOrders->count();
            $cancelledCount = (clone $todayOrders)
                ->where('status', OrderStatus::Cancelled)
                ->count();

            // Top selling item
            $topItemText = 'لا توجد طلبات مسجلة اليوم';
            if ($completedOrders->isNotEmpty()) {
                $topItem = OrderItem::whereIn('order_id', $completedOrders->pluck('id'))
                    ->selectRaw('item_name_snapshot, sum(quantity) as total_qty')
                    ->groupBy('item_name_snapshot')
                    ->orderByDesc('total_qty')
                    ->first();

                if ($topItem) {
                    $topItemText = "{$topItem->item_name_snapshot} ({$topItem->total_qty} طلب)";
                }
            }

            // Average prep time (no synthetic fallback when no ready orders exist)
            $prepTimes = $completedOrders
                ->filter(fn($o) => $o->ready_at && $o->created_at)
                ->map(fn($o) => $o->created_at->diffInMinutes($o->ready_at));
            $avgPrepText = $prepTimes->isNotEmpty()
                ? round($prepTimes->average()) . ' دقيقة'
                : 'لا توجد بيانات كافية';

            // New CRM loyalty customers
            $newCustomers = RestaurantCustomer::where('restaurant_id', $restaurant->id)
                ->where('created_at', '>=', now()->startOfDay())
                ->count();

            // If test mode and no sales today, provide realistic demonstration numbers
            if ($this->option('test') && $ordersCount === 0) {
                $totalSalesStr = '4850000.00';
                $ordersCount = 38;
                $topItemText = 'شاورما عربي دبل اكسترا (24 طلب)';
                $avgPrepText = '11 دقيقة';
                $cancelledCount = 0;
                $newCustomers = 14;
            }

            $currency = MoneyCalculator::displayCurrency($restaurant->currency);
            $hasFraction = ! str_ends_with($totalSalesStr, '.00');
            $formattedSales = number_format((float) $totalSalesStr, $hasFraction ? 2 : 0);
            $cleanDate = now()->format('Y-m-d');

            $message = "🌙 *ملخص الإيرادات اليومي — مطعم {$restaurant->name}*\n"
                . "📅 التاريخ: {$cleanDate}\n\n"
                . "💰 *إجمالي المبيعات:* {$formattedSales} {$currency} ({$ordersCount} فاتورة)\n"
                . "🏆 *الطبق الأفضل مبيعاً:* {$topItemText}\n"
                . "⏱️ *متوسط سرعة إنجاز المطبخ:* {$avgPrepText}\n"
                . "🔒 *مطابقة الصندوق:* " . ($cancelledCount === 0 ? "مطابق تماماً (صفر فواتير ملغاة ✨)" : "{$cancelledCount} فواتير ملغاة") . "\n"
                . "👥 *زبائن جدد في الـ CRM:* {$newCustomers} عميل\n\n"
                . "✨ منظومة «تَدلّل» تتمنى لكم نوماً هانئاً وموسماً زاخراً بالأرباح غداً!";

            $this->line("Dispatching daily digest to {$restaurant->name} ({$phone})...");

            try {
                if ($restaurant->isWhatsappConnected()) {
                    $res = $gatewayService->sendMessage($restaurant, $phone, $message, false);
                } else {
                    $res = $gatewayService->sendPlatformMessage($phone, $message, false);
                }

                if (!empty($res['success'])) {
                    $sentCount++;
                    $this->info("✓ Successfully sent digest to {$restaurant->name}");
                } else {
                    $this->warn("Failed sending digest to {$restaurant->name}: " . ($res['message'] ?? 'Unknown gateway response'));
                }
            } catch (\Throwable $e) {
                Log::error("Failed to send daily digest to {$restaurant->name}: " . $e->getMessage());
                $this->error("Exception sending digest: " . $e->getMessage());
            }
        }

        $this->info("Completed. Sent {$sentCount} daily revenue digests.");
        return Command::SUCCESS;
    }
}
