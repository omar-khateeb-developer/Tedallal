<?php

namespace App\Services;

use App\Models\WaitlistEntry;
use App\Models\RestaurantTable;
use Illuminate\Support\Facades\Log;

class WaitlistNotificationService
{
    public function __construct(
        private readonly WhatsAppGatewayService $whatsAppGateway
    ) {}

    /**
     * Send welcome WhatsApp notification with tracking link when guest joins the waitlist.
     */
    public function sendJoinedNotification(WaitlistEntry $entry): bool
    {
        $restaurant = $entry->restaurant;
        if (! $restaurant || ! $restaurant->isWhatsappConnected()) {
            Log::info("Waitlist WhatsApp skipped: Restaurant {$restaurant?->id} WhatsApp not connected.");
            return false;
        }

        $phone = $this->cleanPhoneNumber($entry->guest_phone);
        if (! $phone) {
            return false;
        }

        $trackingUrl = $entry->getTrackingUrl();
        $message = "مرحباً بك {$entry->guest_name} في *{$restaurant->name}* ✨\n\n"
            . "تم تسجيلك بنجاح في قائمة الانتظار الذكية! 📋\n"
            . "🔢 رقمك في الطابور: *#{$entry->queue_number}*\n"
            . "👥 عدد الأفراد: {$entry->party_size}\n"
            . "⏱️ الوقت التقديري المتوقع: {$entry->estimated_wait_minutes} دقيقة\n\n"
            . "📲 يمكنك متابعة دورك لحظة بلحظة وتصفح المنيو لاختيار وجبتك مسبقاً عبر الرابط التالي:\n"
            . "{$trackingUrl}\n\n"
            . "سنرسل لك إشعاراً فور جهوزية طاولتك بإذن الله. نتمنى لك أوقاتاً ممتعة!";

        $result = $this->whatsAppGateway->sendMessage($restaurant, $phone, $message);
        return $result['success'] ?? false;
    }

    /**
     * Send WhatsApp notification when a table becomes ready for the waiting guest.
     */
    public function sendTableReadyNotification(WaitlistEntry $entry, ?RestaurantTable $table = null): bool
    {
        $restaurant = $entry->restaurant;
        if (! $restaurant || ! $restaurant->isWhatsappConnected()) {
            return false;
        }

        $phone = $this->cleanPhoneNumber($entry->guest_phone);
        if (! $phone) {
            return false;
        }

        $tableName = $table ? $table->name : ($entry->assignedTable?->name ?? 'طاولتك');

        $message = "أهلاً بك {$entry->guest_name} 🌟\n\n"
            . "طاولتك *({$tableName})* في *{$restaurant->name}* أصبحت جاهزة في انتظارك الآن! 🎉\n\n"
            . "📍 يرجى التوجه إلى قسم الاستقبال خلال *5 دقائق* لتأكيد جلوسك.\n\n"
            . "نتمنى لك وجبة شهية وأسعد اللحظات! 🍽️✨";

        $result = $this->whatsAppGateway->sendMessage($restaurant, $phone, $message);
        return $result['success'] ?? false;
    }

    private function cleanPhoneNumber(string $phone): ?string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (empty($clean)) {
            return null;
        }

        // Convert Syrian local format 09xxxxxxxx to 9639xxxxxxxx
        if (str_starts_with($clean, '09') && strlen($clean) === 10) {
            $clean = '963' . substr($clean, 1);
        }

        return $clean;
    }
}
