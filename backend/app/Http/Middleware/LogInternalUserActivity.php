<?php

namespace App\Http\Middleware;

use App\Models\Enquiry;
use App\Models\TrainingMatrixEntry;
use App\Models\User;
use App\Services\ActivityLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogInternalUserActivity
{
    /** @var list<string> */
    private array $skipRoutes = [
        'login',
        'logout',
        'admin.activity.index',
        // Logged explicitly with full details in controllers.
        'admin.enquiries.destroy',
        'admin.training-matrix.store',
        'admin.training-matrix.update',
        'admin.training-matrix.destroy',
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
        $subject = $this->subjectFromRoute($request);
        $description = $this->descriptionFromStatus($status)
            ?? $this->descriptionFromRoute($routeName, $method, $subject);

        ActivityLogger::log(
            action: $this->actionFromMethod($method, $routeName),
            description: $description,
            user: $user,
            request: $request,
            subjectType: $subject ? $subject::class : null,
            subjectId: $subject?->getKey() ? (int) $subject->getKey() : null,
            properties: $this->propertiesFor($routeName, $status, $subject),
        );
    }

    private function subjectFromRoute(Request $request): ?Model
    {
        $route = $request->route();
        if (! $route) {
            return null;
        }

        foreach (['enquiry', 'trainingMatrix', 'training_matrix', 'user', 'feedback'] as $key) {
            $param = $route->parameter($key);
            if ($param instanceof Model) {
                return $param;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function propertiesFor(?string $routeName, mixed $status, ?Model $subject): array
    {
        $properties = [
            'status' => is_string($status) ? $status : null,
            'area' => $this->areaFromRoute($routeName),
        ];

        if ($subject instanceof Enquiry) {
            $properties['enquiry_id'] = (int) $subject->id;
            $properties['name'] = $subject->name;
            $properties['email'] = $subject->email;
            $properties['area'] = 'Enquiry';
        }

        if ($subject instanceof TrainingMatrixEntry) {
            $properties['matrix_entry_id'] = (int) $subject->id;
            $properties['sector'] = $subject->sector;
            $properties['course'] = $subject->course;
            $properties['course_value'] = $subject->course_value;
            $properties['format'] = $subject->format;
            $properties['sub_option'] = $subject->sub_option;
            $properties['area'] = 'Training Matrix';
        }

        if ($subject instanceof User) {
            $properties['user_name'] = $subject->name;
            $properties['user_email'] = $subject->email;
            $properties['area'] = 'Users';
        }

        return array_filter(
            $properties,
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    private function areaFromRoute(?string $routeName): ?string
    {
        $routeName = (string) $routeName;

        return match (true) {
            str_contains($routeName, 'training-matrix') => 'Training Matrix',
            str_contains($routeName, 'enquiries') => 'Enquiry',
            str_contains($routeName, 'users') => 'Users',
            str_contains($routeName, 'settings') => 'Settings',
            str_contains($routeName, 'feedback') => 'Feedback',
            default => null,
        };
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

    private function descriptionFromRoute(?string $routeName, string $method, ?Model $subject = null): string
    {
        $labels = [
            'admin.enquiries.create' => 'Started enquiry form for a customer',
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
            'admin.enquiries.forge.resync' => 'Resynced enquiry to Forge',
            'admin.enquiries.forge.cancel' => 'Cancelled Forge booking',
            'admin.enquiries.destroy' => 'Deleted enquiry',
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

        $base = ($routeName && isset($labels[$routeName]))
            ? $labels[$routeName]
            : ($routeName
                ? ucfirst(str_replace(['admin.', '.', '-', '_'], ['', ' ', ' ', ' '], $routeName))
                : $method.' request');

        if ($subject instanceof Enquiry) {
            return rtrim($base, '.').' for enquiry #'.$subject->id
                .(trim((string) $subject->name) !== '' ? ' ('.trim((string) $subject->name).')' : '').'.';
        }

        if ($subject instanceof TrainingMatrixEntry) {
            $label = trim(implode(' · ', array_filter([
                trim((string) $subject->sector),
                trim((string) $subject->course),
                trim((string) $subject->format),
                trim((string) $subject->sub_option),
            ])));

            return rtrim($base, '.').($label !== '' ? ': '.$label : '').'.';
        }

        if ($subject instanceof User) {
            return rtrim($base, '.').' ('.$subject->name.').';
        }

        return $base;
    }

    private function actionFromMethod(string $method, ?string $routeName): string
    {
        $routeName = (string) $routeName;

        if (str_contains($routeName, 'retry')) {
            return 'retry';
        }
        if (str_contains($routeName, 'resend')) {
            return 'resend';
        }
        if (str_contains($routeName, 'cancel')) {
            return 'cancel';
        }
        if (str_contains($routeName, 'sync') || str_contains($routeName, 'resync')) {
            return 'sync';
        }
        if (str_contains($routeName, 'export')) {
            return 'export';
        }
        if (str_contains($routeName, 'destroy') || $method === 'DELETE') {
            return 'delete';
        }
        if (str_contains($routeName, '.update') || str_contains($routeName, 'booking.update') || in_array($method, ['PUT', 'PATCH'], true)) {
            return 'update';
        }
        if (str_contains($routeName, '.store')) {
            return 'create';
        }

        return match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'action',
        };
    }
}
