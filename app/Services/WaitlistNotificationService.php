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
        if (! $restaurant) {
            return false;
        }

        $phone = $this->cleanPhoneNumber($entry->guest_phone);
        if (! $phone) {
            return false;
        }

        $trackingUrl = $entry->getTrackingUrl();
        $message = "أهلاً بحضرتك {$entry->guest_name} في *{$restaurant->name}* ✨\n\n"
            . "تم تسجيلك بنجاح في قائمة الانتظار الذكية! 📋\n"
            . "🔢 رقمك في الطابور: *#{$entry->queue_number}*\n"
            . "👥 عدد الأفراد: {$entry->party_size}\n"
            . "⏱️ الوقت التقديري المتوقع: {$entry->estimated_wait_minutes} دقيقة\n\n"
            . "📲 يمكن لحضرتك متابعة دورك لحظة بلحظة وتصفح المنيو لاختيار وجبتك مسبقاً عبر الرابط التالي:\n"
            . "{$trackingUrl}\n\n"
            . "سنرسل لك إشعاراً فور جهوزية طاولتك بإذن الله. نتمنى لحضرتك أوقاتاً ممتعة!";

        $result = $this->whatsAppGateway->sendTransactionalWithFallback($restaurant, $phone, $message);
        return ! empty($result['success']);
    }

    /**
     * Send WhatsApp notification when a table becomes ready for the waiting guest.
     */
    public function sendTableReadyNotification(WaitlistEntry $entry, ?RestaurantTable $table = null): bool
    {
        $restaurant = $entry->restaurant;
        if (! $restaurant) {
            return false;
        }

        $phone = $this->cleanPhoneNumber($entry->guest_phone);
        if (! $phone) {
            return false;
        }

        $tableName = $table ? $table->name : ($entry->assignedTable?->name ?? 'طاولتك');

        $message = "أهلاً بحضرتك {$entry->guest_name} 🌟\n\n"
            . "طاولة حضرتك *({$tableName})* في *{$restaurant->name}* أصبحت جاهزة في انتظاركم الآن! 🎉\n\n"
            . "📍 يرجى التوجه إلى قسم الاستقبال خلال *5 دقائق* لتأكيد الجلوس.\n\n"
            . "نتمنى لحضرتك وجبة شهية وأسعد اللحظات! 🍽️✨";

        $result = $this->whatsAppGateway->sendTransactionalWithFallback($restaurant, $phone, $message);
        return ! empty($result['success']);
    }

    private function cleanPhoneNumber(string $phone): ?string
    {
        $clean = \App\Support\PhoneNormalizer::toWhatsAppDigits($phone);
        return $clean !== '' ? $clean : null;
    }
}
