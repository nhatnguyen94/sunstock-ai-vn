<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Validation rules shared by every place an account is created or edited
 * (register, profile, password reset, admin password change), so the policy
 * cannot drift between them.
 */
final class AuthRules
{
    /** Letters (any language, so Vietnamese works), digits, space . _ - ; no HTML metacharacters. */
    public const USERNAME_REGEX = '/^[\p{L}\p{N}][\p{L}\p{N} ._-]*$/u';

    public const MOBILE_REGEX = '/^[+(]?[0-9][0-9 ().-]{5,19}$/';

    /**
     * 8–128 characters with at least one letter and one digit. The upper bound matters: bcrypt only uses the
     * first 72 bytes and a megabyte-long "password" is just a free way to make the server hash garbage.
     */
    public static function password(): Password
    {
        return Password::min(8)->max(128)->letters()->numbers();
    }

    /** @return list<string|\Illuminate\Contracts\Validation\Rule> */
    public static function username(): array
    {
        return ['required', 'string', 'min:3', 'max:100', 'regex:' . self::USERNAME_REGEX];
    }

    /** @return list<string> */
    public static function mobile(): array
    {
        return ['nullable', 'string', 'regex:' . self::MOBILE_REGEX];
    }

    /** Lower-cased, trimmed e-mail; anything that is not a string is left alone for the validator to reject. */
    public static function normalizeEmail(mixed $email): mixed
    {
        return is_string($email) ? Str::lower(trim($email)) : $email;
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'username.required' => 'Tên người dùng là bắt buộc.',
            'username.min' => 'Tên người dùng phải có ít nhất 3 ký tự.',
            'username.max' => 'Tên người dùng không được quá 100 ký tự.',
            'username.regex' => 'Tên người dùng chỉ được gồm chữ, số, khoảng trắng và các ký tự . _ -',
            'mobile.regex' => 'Số điện thoại không hợp lệ.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.max' => 'Mật khẩu không được quá 128 ký tự.',
            'password.letters' => 'Mật khẩu phải có ít nhất một chữ cái.',
            'password.numbers' => 'Mật khẩu phải có ít nhất một chữ số.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ];
    }
}
