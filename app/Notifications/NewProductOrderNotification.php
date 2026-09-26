<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NewProductOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $seller
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(
        private readonly array $order,
        private readonly array $seller,
        private readonly array $items
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(
        object $notifiable
    ): MailMessage {
        $frontendUrl = rtrim(
            (string) config(
                'app.frontend_url',
                'https://www.rushpi.com'
            ),
            '/'
        );

        return (new MailMessage())
            ->subject(
                'New RushPi order '.$this->order['order_number']
            )
            ->view(
                'emails.seller-new-product-order',
                [
                    'sellerName' =>
                        $this->seller['name'],
                    'recipientName' =>
                        $notifiable->name
                            ?? 'RushPi Seller',
                    'order' => $this->order,
                    'items' => $this->items,
                    'ordersUrl' =>
                        $frontendUrl.'/seller/orders',
                    'logoUrl' =>
                        $frontendUrl.'/rushpii-01.png',
                ]
            );
    }
}
