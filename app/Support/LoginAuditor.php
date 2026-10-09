<?php

namespace App\Support;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;
use Throwable;

/**
 * Keeps a row per sign-in attempt for Admin > Bảo mật: who (or which typed e-mail), from which address and browser, which door
 * (the site or /admin) and whether it worked. Listens to the framework's own events, so neither login controller knows about it.
 * The answer a visitor gets is never affected: this only records.
 */
class LoginAuditor
{
    public function succeeded(Login $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->record($user?->id, $user?->email, true);
    }

    public function failed(Failed $event): void
    {
        $typed = $event->credentials['email'] ?? null;

        $this->record($event->user instanceof User ? $event->user->id : null, is_string($typed) ? $typed : null, false);
    }

    private function record(?int $userId, ?string $email, bool $success): void
    {
        try {
            LoginAttempt::create([
                'user_id' => $userId,
                'email' => $email !== null ? mb_substr(mb_strtolower(trim($email)), 0, 120) : null,
                'ip' => Request::ip(),
                'user_agent' => ($agent = Request::userAgent()) !== null ? mb_substr($agent, 0, 200) : null,
                'success' => $success,
                'door' => Request::is('admin*') ? 'admin' : 'web',
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // bookkeeping must never break a sign-in
        }
    }
}
