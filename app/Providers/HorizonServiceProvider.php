<?php

namespace App\Providers;

use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register Horizon's authorization.
     *
     * Two things the package default does NOT do, both deliberate here:
     * 1. Checks `User::hasPermission('manage-queue')` directly instead of
     *    going through `Gate::check('viewHorizon', ...)` — this app's global
     *    `Gate::before()` (AppServiceProvider) intercepts EVERY ability name
     *    and resolves it against the `permissions` table; since no permission
     *    is literally named "viewHorizon", that hook always returned false
     *    and short-circuited before a locally `Gate::define()`d 'viewHorizon'
     *    callback ever ran. Checking the permission directly here sidesteps
     *    that entirely — single source of truth is the `manage-queue`
     *    permission, same one used in the sidebar's `@can('manage-queue')`.
     * 2. Does NOT bypass for `app()->environment('local')` like the package
     *    default — this "local" Docker env is reachable over a real hostname
     *    (sunstock-local.dev) on all interfaces, not loopback-only like
     *    MySQL's port binding, so the local-env bypass would leave the
     *    dashboard wide open to anyone who can reach that hostname.
     */
    protected function authorization(): void
    {
        Horizon::auth(function ($request) {
            return $request->user()?->hasPermission('manage-queue') ?? false;
        });
    }
}
