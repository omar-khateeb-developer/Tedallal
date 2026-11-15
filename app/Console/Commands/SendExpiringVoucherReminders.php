<?php

namespace App\Console\Commands;

use App\Models\PromoCode;
use App\Services\CustomerCrmService;
use App\Services\WhatsAppGatewayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendExpiringVoucherReminders extends Command
{
    protected $signature = 'crm:send-expiring-reminders';

    protected $description = 'Send automated 24-hour expiration reminders to customers via connected WhatsApp gateway';

    public function handle(
        CustomerCrmService $crmService,
        WhatsAppGatewayService $gatewayService
    ): int {
        $this->info('Checking for vouchers expiring in approximately 24 hours...');

        $now = now();
        $windowStart = $now->copy()->addHours(18);
        $windowEnd = $now->copy()->addHours(36);

        $expiringPromos = PromoCode::withoutGlobalScopes()
            ->whereNotNull('customer_id')
            ->where('is_active', true)
            ->where('is_expiring_reminded', false)
            ->where('times_used', 0)
            ->whereBetween('expires_at', [$windowStart, $windowEnd])
            ->with(['customer', 'restaurant'])
            ->get();

        $this->info("Found {$expiringPromos->count()} promo codes expiring soon.");

        $sentCount = 0;

        foreach ($expiringPromos as $promo) {
            $restaurant = $promo->restaurant;
            $customer = $promo->customer;

            if (! $restaurant || ! $customer) {
                continue;
            }

            if (! $restaurant->hasWhatsappRemindersFeature() || ! $restaurant->isWhatsappConnected()) {
                continue;
            }

            if (! (bool) $restaurant->whatsapp_expiring_reminders_enabled) {
                continue;
            }

            $message = $crmService->buildExpiringSoonMessage($restaurant, $customer, $promo);

            $this->line("Dispatching FOMO reminder to customer {$customer->name} ({$customer->phone}) for restaurant {$restaurant->name}...");

            $res = $gatewayService->sendMessage($restaurant, $customer->phone, $message, true);

            if (! empty($res['success'])) {
                $promo->update(['is_expiring_reminded' => true]);
                $sentCount++;
                $this->info("Sent successfully to {$customer->phone}");

                // Anti-ban humanized delay between messages (5-10 seconds)
                $delay = random_int(5, 10);
                sleep($delay);
            } else {
                $this->warn("Failed sending to {$customer->phone}: " . ($res['message'] ?? 'Unknown error'));
            }
        }

        $this->info("Completed. Sent {$sentCount} expiring voucher reminders.");
        return Command::SUCCESS;
    }
}

