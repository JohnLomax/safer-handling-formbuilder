<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogInternalUserActivity
{
    /** @var list<string> */
    private array $skipRoutes = [
        'login',
        'logout',
        'admin.activity.index',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->maybeLog($request, $response);
        } catch (\Throwable) {
            // Ignore logging failures.
        }

        return $response;
    }

    private function maybeLog(Request $request, Response $response): void
    {
        $user = $request->user();
        if (! $user || ! $user->is_admin) {
            return;
        }

        $method = strtoupper($request->method());
        if (! in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $routeName = $request->route()?->getName();
        if ($routeName && in_array($routeName, $this->skipRoutes, true)) {
            return;
        }

        if ($response->getStatusCode() >= 400) {
            return;
        }

        if ($request->session()->has('errors')) {
            return;
        }

        $status = $request->session()->get('status');
        $description = $this->descriptionFromStatus($status)
            ?? $this->descriptionFromRoute($routeName, $method);

        ActivityLogger::log(
            action: $this->actionFromMethod($method, $routeName),
            description: $description,
            user: $user,
            request: $request,
            properties: [
                'status' => is_string($status) ? $status : null,
            ],
        );
    }

    private function descriptionFromStatus(mixed $status): ?string
    {
        if (! is_string($status) || $status === '') {
            return null;
        }

        return match ($status) {
            'profile-updated' => 'Updated profile details',
            'password-updated' => 'Updated password',
            'verification-link-sent' => 'Requested email verification link',
            default => $status,
        };
    }

    private function descriptionFromRoute(?string $routeName, string $method): string
    {
        $labels = [
            'admin.enquiries.booking.update' => 'Updated enquiry booking details',
            'admin.enquiries.retry.quote-email' => 'Retried quote email',
            'admin.enquiries.retry.lead-notification' => 'Retried lead notification',
            'admin.enquiries.retry.resume-email' => 'Retried resume email',
            'admin.enquiries.retry.booking-email' => 'Retried booking email',
            'admin.enquiries.resend.resume-email' => 'Resent resume email',
            'admin.enquiries.resend.booking-email' => 'Resent booking email',
            'admin.enquiries.retry.xero-invoice' => 'Retried Xero invoice',
            'admin.enquiries.sync.xero-invoice-sent' => 'Synced Xero invoice sent',
            'admin.enquiries.retry.kajabi-enroll' => 'Retried Kajabi enrollment',
            'admin.enquiries.retry.event' => 'Retried enquiry event',
            'admin.feedback.resolve' => 'Resolved feedback',
            'admin.feedback.export' => 'Exported feedback',
            'admin.users.store' => 'Created user',
            'admin.users.update' => 'Updated user',
            'admin.users.destroy' => 'Deleted user',
            'admin.settings.update' => 'Updated integration settings',
            'admin.settings.xero.connect' => 'Started Xero connection',
            'admin.settings.xero.callback' => 'Completed Xero connection',
            'admin.settings.xero.disconnect' => 'Disconnected Xero',
            'admin.training-matrix.store' => 'Created training matrix entry',
            'admin.training-matrix.update' => 'Updated training matrix entry',
            'admin.training-matrix.destroy' => 'Deleted training matrix entry',
            'profile.update' => 'Updated profile details',
            'profile.destroy' => 'Deleted account',
        ];

        if ($routeName && isset($labels[$routeName])) {
            return $labels[$routeName];
        }

        if ($routeName) {
            return ucfirst(str_replace(['admin.', '.', '-', '_'], ['', ' ', ' ', ' '], $routeName));
        }

        return $method.' request';
    }

    private function actionFromMethod(string $method, ?string $routeName): string
    {
        if ($routeName && str_contains($routeName, 'retry')) {
            return 'retry';
        }
        if ($routeName && str_contains($routeName, 'resend')) {
            return 'resend';
        }
        if ($routeName && str_contains($routeName, 'sync')) {
            return 'sync';
        }
        if ($routeName && str_contains($routeName, 'export')) {
            return 'export';
        }

        return match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'action',
        };
    }
}
