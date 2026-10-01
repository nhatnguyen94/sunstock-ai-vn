<?php

namespace Tests\Feature\Support;

use App\Support\AuthRules;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** The shared account-field rules, tested directly so the policy cannot silently loosen. */
class AuthRulesTest extends TestCase
{
    private function passes(string $field, mixed $value): bool
    {
        $rules = match ($field) {
            'username' => AuthRules::username(),
            'mobile' => AuthRules::mobile(),
            'password' => ['required', 'string', AuthRules::password()],
        };

        return Validator::make([$field => $value], [$field => $rules])->passes();
    }

    /** @return array<string, array{string}> */
    public static function goodUsernames(): array
    {
        return array_map(fn ($v) => [$v], [
            'sunadmin' => 'sunadmin', 'demo1' => 'demo1', 'vietnamese' => 'Nguyễn Văn A', 'with dot' => 'john.doe', 'hyphen underscore' => 'a-b_c',
            'digits only' => '123456', 'cjk' => '山田太郎',
        ]);
    }

    /** @return array<string, array{string}> */
    public static function badUsernames(): array
    {
        return array_map(fn ($v) => [$v], [
            'script' => '<script>alert(1)</script>', 'img' => '<img src=x onerror=alert(1)>', 'double quote' => 'a"b', 'single quote' => "a'b",
            'backtick' => 'a`b', 'ampersand' => 'a&b', 'angle' => 'a<b', 'slash' => 'a/b', 'backslash' => 'a\\b', 'semicolon' => 'a;b',
            'leading space' => ' abc', 'leading dot' => '.abc', 'newline' => "abc\ndef", 'null byte' => "abc\0def", 'tab' => "a\tb",
            'at sign' => 'a@b', 'percent' => '100%', 'too short' => 'ab', 'empty' => '', 'too long' => str_repeat('a', 101),
            'rtl override' => "abc\u{202E}def", 'zero width space' => "abc\u{200B}def",
        ]);
    }

    #[Group('authSecurity')]
    #[DataProvider('goodUsernames')]
    public function test_ordinary_names_are_accepted(string $name): void
    {
        $this->assertTrue($this->passes('username', $name), $name);
    }

    #[Group('authSecurity')]
    #[DataProvider('badUsernames')]
    public function test_names_that_could_carry_markup_or_control_characters_are_rejected(string $name): void
    {
        $this->assertFalse($this->passes('username', $name), json_encode($name));
    }

    #[Group('authSecurity')]
    public function test_mobile_accepts_phone_formats_and_rejects_everything_else(): void
    {
        foreach (['0912345678', '+84 912 345 678', '(028) 3822-1234', '091-234-5678', null] as $ok) {
            $this->assertTrue($this->passes('mobile', $ok), (string) $ok);
        }
        foreach (['<b>1</b>', 'abc', '12345', str_repeat('1', 21), '0912345678; DROP', "0912\n345678", '+', '--------'] as $bad) {
            $this->assertFalse($this->passes('mobile', $bad), $bad);
        }
    }

    #[Group('authSecurity')]
    public function test_password_policy_boundaries(): void
    {
        $this->assertTrue($this->passes('password', 'abcdefg1'));                       // exactly 8, letter + digit
        $this->assertTrue($this->passes('password', str_repeat('a', 127) . '1'));       // exactly 128
        $this->assertFalse($this->passes('password', 'abcdef1'));                        // 7
        $this->assertFalse($this->passes('password', str_repeat('a', 128) . '1'));       // 129
        $this->assertFalse($this->passes('password', 'abcdefgh'));                       // no digit
        $this->assertFalse($this->passes('password', '12345678'));                       // no letter
        $this->assertFalse($this->passes('password', ''));
        $this->assertFalse($this->passes('password', ['abcdefg1']));
    }

    #[Group('authSecurity')]
    public function test_normalize_email_trims_lowercases_and_leaves_non_strings_for_the_validator(): void
    {
        $this->assertSame('a@b.test', AuthRules::normalizeEmail("  A@B.Test \n"));
        $this->assertSame(['x'], AuthRules::normalizeEmail(['x']));
        $this->assertNull(AuthRules::normalizeEmail(null));
    }
}
