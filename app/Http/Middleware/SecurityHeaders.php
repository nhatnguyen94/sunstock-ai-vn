<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser-side protections for every web response.
 *
 * The CSP here is deliberately limited to the directives that do not depend on which scripts/styles a page loads
 * (the site uses CDN assets and inline page-data scripts, so a script-src policy would need a nonce rollout):
 * framing, <base>, form targets and plugins. That is what closes clickjacking on the login form and stops an
 * injected <form action="https://evil"> from receiving credentials.
 */
class SecurityHeaders
{
    /** Pages whose URL or body carries a secret: never cached, and the reset token must not leak through Referer. */
    private const SENSITIVE = ['login', 'register', 'forgot-password', 'reset-password*', 'email/verify*', 'admin/login'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', $request->is('reset-password*', 'email/verify*') ? 'no-referrer' : 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $h->set('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");

        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=15552000');
        }

        if ($request->is(...self::SENSITIVE)) {
            $h->set('Cache-Control', 'no-store, private');
        }

        $h->remove('X-Powered-By');

        return $response;
    }
}
