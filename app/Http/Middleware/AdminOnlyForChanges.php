<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Support\TransformerResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Account, role and permission administration is read-only for everybody except the `admin` role. Viewing the
 * lists stays under the usual `can:` permission (so it can still be delegated), but creating, editing, deleting
 * and the create/edit forms themselves need the admin role. This is what stops a delegated `manage-users` /
 * `manage-roles` holder from promoting themselves.
 */
class AdminOnlyForChanges
{
    public function handle(Request $request, Closure $next): Response
    {
        $readOnly = $request->isMethodSafe() && ! $request->routeIs('*.create', '*.edit');

        if (! $readOnly && ! Auth::user()?->hasRole(Role::ADMIN)) {
            TransformerResponse::abortWith(TransformerResponse::HTTP_FORBIDDEN, TransformerResponse::ADMIN_ONLY_CHANGES_MESSAGE);
        }

        return $next($request);
    }
}
