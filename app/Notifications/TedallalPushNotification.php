<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TedallalPushNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $url = '/admin/dashboard',
        public ?int $orderId = null,
        public ?int $tableId = null,
        public array $data = []
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $message = (new WebPushMessage)
            ->title($this->title)
            ->icon('/favicon-192x192.png')
            ->badge('/favicon-96x96.png')
            ->body($this->body)
            ->data(array_merge([
                'url' => $this->url,
                'order_id' => $this->orderId,
                'table_id' => $this->tableId,
            ], $this->data))
            ->vibrate([200, 100, 200, 100, 300, 150, 400])
            ->tag($this->orderId ? "tedallal-order-{$this->orderId}" : 'tedallal-push-' . uniqid())
            ->action('معاينة فورية', 'open_order')
            ->action('إغلاق', 'dismiss');

        if (method_exists($message, 'options')) {
            $message->options([
                'renotify' => true,
                'requireInteraction' => true,
            ]);
        }

        return $message;
    }
}
