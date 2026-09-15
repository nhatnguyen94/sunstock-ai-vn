<?php

namespace Tests\Unit\Frontend\Controllers;

use App\Frontend\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test: Password::INVALID_USER etc. are plain string class
 * constants on the facade (not resolved dynamically), so this needs
 * neither a booted app nor a database. See tests/README (docs/TESTING.md).
 */
class PasswordResetControllerTest extends TestCase
{
    #[Group('auth')]
    public function test_translates_invalid_user_status(): void
    {
        $message = (new PasswordResetController())->translateStatus(Password::INVALID_USER);

        $this->assertStringContainsString('Không tìm thấy tài khoản', $message);
    }

    #[Group('auth')]
    public function test_translates_invalid_token_status(): void
    {
        $message = (new PasswordResetController())->translateStatus(Password::INVALID_TOKEN);

        $this->assertStringContainsString('không hợp lệ hoặc đã hết hạn', $message);
    }

    #[Group('auth')]
    public function test_translates_throttled_status(): void
    {
        $message = (new PasswordResetController())->translateStatus(Password::RESET_THROTTLED);

        $this->assertStringContainsString('vừa yêu cầu', $message);
    }

    #[Group('auth')]
    public function test_translates_unknown_status_with_a_generic_fallback_message(): void
    {
        $message = (new PasswordResetController())->translateStatus('something-unexpected');

        $this->assertStringContainsString('Có lỗi xảy ra', $message);
    }
}
