<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

class ActivityLogger
{
    public static function log(
        string $action,
        string $description,
        ?User $user = null,
        ?Request $request = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $properties = null,
    ): void {
        try {
            $request ??= request();
            $user ??= $request?->user();

            ActivityLog::query()->create([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'user_email' => $user?->email,
                'action' => $action,
                'description' => $description,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'route_name' => $request?->route()?->getName(),
                'method' => $request?->method(),
                'url' => $request ? mb_substr($request->fullUrl(), 0, 1000) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 1000) : null,
                'properties' => $properties,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Never break the app because of audit logging.
        }
    }

    public static function login(User $user, ?Request $request = null): void
    {
        $request ??= request();

        self::log(
            action: 'login',
            description: 'Signed in',
            user: $user,
            request: $request,
            properties: [
                'ip' => $request?->ip(),
            ],
        );
    }

    public static function logout(User $user, ?Request $request = null): void
    {
        $request ??= request();

        self::log(
            action: 'logout',
            description: 'Signed out',
            user: $user,
            request: $request,
            properties: [
                'ip' => $request?->ip(),
            ],
        );
    }
}
