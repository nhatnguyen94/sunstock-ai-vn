<?php

namespace Tests\Feature\Notifications;

use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Models\User;
use App\Notifications\PortfolioAlertNotification;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * "Feature" because toMail() calls the url() helper, which needs a booted
 * Laravel container. No database is touched — models are built in memory.
 */
class PortfolioAlertNotificationTest extends TestCase
{
    private function makeItem(array $attrs = []): PortfolioItem
    {
        $portfolio = new Portfolio(['name' => 'Long-term Growth']);
        $portfolio->id = 5;

        $item = new PortfolioItem(array_merge([
            'portfolio_id' => 5,
            'stock_symbol' => 'ACB',
            'stock_name' => 'ACB',
            'quantity' => 100,
            'buy_price' => 20,
            'current_price' => 25,
            'target_price' => 24,
            'stop_loss_price' => 18,
        ], $attrs));
        $item->id = 1;
        $item->setRelation('portfolio', $portfolio);

        return $item;
    }

    #[Group('portfolio-alerts')]
    public function test_via_uses_mail_channel_only(): void
    {
        $notification = new PortfolioAlertNotification($this->makeItem(), 'target');

        $this->assertSame(['mail'], $notification->via(new User()));
    }

    #[Group('portfolio-alerts')]
    public function test_target_alert_mail_mentions_symbol_and_target_price(): void
    {
        $item = $this->makeItem(['current_price' => 25, 'target_price' => 24]);
        $user = new User(['name' => 'Sun']);

        $mail = (new PortfolioAlertNotification($item, 'target'))->toMail($user);

        $this->assertStringContainsString('ACB', $mail->subject);
        $this->assertStringContainsString('đạt giá mục tiêu', $mail->subject);
        $introText = implode(' ', $mail->introLines);
        $this->assertStringContainsString('ACB', $introText);
        $this->assertStringContainsString('Long-term Growth', $introText);
        $this->assertStringContainsString(number_format(24, 0, ',', '.'), $introText);
        $this->assertSame(url('/portfolio/5'), $mail->actionUrl);
    }

    #[Group('portfolio-alerts')]
    public function test_stop_loss_alert_mail_mentions_symbol_and_stop_loss_price(): void
    {
        $item = $this->makeItem(['current_price' => 15, 'stop_loss_price' => 18]);
        $user = new User(['name' => 'Sun']);

        $mail = (new PortfolioAlertNotification($item, 'stop_loss'))->toMail($user);

        $this->assertStringContainsString('ACB', $mail->subject);
        $this->assertStringContainsString('cắt lỗ', $mail->subject);
        $introText = implode(' ', $mail->introLines);
        $this->assertStringContainsString(number_format(18, 0, ',', '.'), $introText);
    }
}
