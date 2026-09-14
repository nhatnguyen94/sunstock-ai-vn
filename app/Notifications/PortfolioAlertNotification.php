<?php

namespace App\Notifications;

use App\Models\PortfolioItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortfolioAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $type  'target' or 'stop_loss'
     */
    public function __construct(
        private PortfolioItem $item,
        private string $type
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $item = $this->item;
        $isTarget = $this->type === 'target';

        $subject = $isTarget
            ? "🎯 {$item->stock_symbol} đã đạt giá mục tiêu"
            : "⚠️ {$item->stock_symbol} đã chạm ngưỡng cắt lỗ";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Xin chào ' . $notifiable->name . ',');

        if ($isTarget) {
            $message->line("Cổ phiếu **{$item->stock_symbol}** trong danh mục \"{$item->portfolio->name}\" đã đạt giá mục tiêu bạn đặt ra.")
                ->line('Giá mục tiêu: ' . number_format((float) $item->target_price, 0, ',', '.') . ' VNĐ')
                ->line('Giá hiện tại: ' . number_format((float) $item->current_price, 0, ',', '.') . ' VNĐ');
        } else {
            $message->line("Cổ phiếu **{$item->stock_symbol}** trong danh mục \"{$item->portfolio->name}\" đã chạm ngưỡng cắt lỗ bạn đặt ra.")
                ->line('Giá cắt lỗ: ' . number_format((float) $item->stop_loss_price, 0, ',', '.') . ' VNĐ')
                ->line('Giá hiện tại: ' . number_format((float) $item->current_price, 0, ',', '.') . ' VNĐ');
        }

        return $message
            ->action('Xem danh mục', url('/portfolio/' . $item->portfolio_id))
            ->line('Đây là email tự động từ Sun Stock App.');
    }
}
