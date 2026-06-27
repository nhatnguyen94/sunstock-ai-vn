<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Log an activity event.
     *
     * @param string      $eventType   One of the keys in ActivityLog::iconConfig()
     * @param string      $description Human-readable description
     * @param array       $properties  Optional extra data stored as JSON
     * @param User|null   $user        Override authenticated user (pass null to use current)
     */
    public static function log(
        string $eventType,
        string $description,
        array $properties = [],
        ?User $user = null
    ): void {
        try {
            $actor = $user ?? Auth::user();

            ActivityLog::create([
                'user_id'    => $actor?->id,
                'user_name'  => $actor?->name ?? 'System',
                'event_type' => $eventType,
                'description'=> mb_substr($description, 0, 500),
                'properties' => empty($properties) ? null : $properties,
                'ip_address' => Request::ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Never let logging crash the main flow
        }
    }
}
