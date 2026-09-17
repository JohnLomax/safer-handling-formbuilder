<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'route_name',
        'method',
        'url',
        'ip_address',
        'user_agent',
        'properties',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'login' => 'Signed in',
            'logout' => 'Signed out',
            'create' => 'Created',
            'update' => 'Updated',
            'delete' => 'Deleted',
            'retry' => 'Retried',
            'resend' => 'Resent',
            'sync' => 'Synced',
            'cancel' => 'Cancelled',
            'export' => 'Exported',
            default => Str::headline(str_replace('_', ' ', (string) $this->action)),
        };
    }

    public function actionPillClass(): string
    {
        return match ($this->action) {
            'login', 'create', 'sync', 'resend' => 'status-pill-success',
            'delete', 'logout', 'cancel' => 'status-pill-muted',
            'retry' => 'status-pill-progress',
            'update' => 'status-pill-progress',
            'export' => 'status-pill-progress',
            default => 'status-pill-progress',
        };
    }

    /**
     * High-level area of the admin this action touched.
     */
    public function areaLabel(): ?string
    {
        $props = is_array($this->properties) ? $this->properties : [];
        if (! empty($props['area']) && is_string($props['area'])) {
            return $props['area'];
        }

        $subject = (string) ($this->subject_type ?? '');
        if (str_contains($subject, 'TrainingMatrixEntry')) {
            return 'Training Matrix';
        }
        if (str_contains($subject, 'Enquiry')) {
            return 'Enquiry';
        }
        if (str_contains($subject, 'User')) {
            return 'Users';
        }
        if (str_contains($subject, 'Feedback')) {
            return 'Feedback';
        }

        $route = (string) ($this->route_name ?? '');
        if (str_contains($route, 'training-matrix')) {
            return 'Training Matrix';
        }
        if (str_contains($route, 'enquiries')) {
            return 'Enquiry';
        }
        if (str_contains($route, 'users')) {
            return 'Users';
        }
        if (str_contains($route, 'settings')) {
            return 'Settings';
        }
        if (str_contains($route, 'feedback')) {
            return 'Feedback';
        }
        if (in_array($this->action, ['login', 'logout'], true)) {
            return 'Account';
        }

        return null;
    }

    /**
     * Extra detail lines for the activity table (from stored properties).
     *
     * @return list<array{label:string,value:string}>
     */
    public function detailRows(): array
    {
        $props = is_array($this->properties) ? $this->properties : [];
        $rows = [];

        $map = [
            'enquiry_id' => 'Enquiry',
            'matrix_entry_id' => 'Matrix row',
            'sector' => 'Sector',
            'course' => 'Course',
            'course_value' => 'Course code',
            'format' => 'Format',
            'sub_option' => 'Course style',
            'name' => 'Name',
            'email' => 'Email',
            'status' => 'Status',
            'monday_item_id' => 'Monday item',
            'user_name' => 'User',
            'user_email' => 'User email',
        ];

        foreach ($map as $key => $label) {
            if (! array_key_exists($key, $props) || $props[$key] === null || $props[$key] === '') {
                continue;
            }
            // Avoid repeating flash status that matches the description.
            if ($key === 'status' && is_string($props[$key]) && $props[$key] === $this->description) {
                continue;
            }
            $value = $props[$key];
            if (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            }
            $rows[] = [
                'label' => $label,
                'value' => is_scalar($value) ? (string) $value : json_encode($value),
            ];
        }

        if (array_key_exists('is_active', $props) && $props['is_active'] !== null) {
            $rows[] = [
                'label' => 'Active on form',
                'value' => $props['is_active'] ? 'Yes' : 'No',
            ];
        }
        if (array_key_exists('kajabi_enabled', $props) && $props['kajabi_enabled'] !== null) {
            $rows[] = [
                'label' => 'Kajabi',
                'value' => $props['kajabi_enabled'] ? 'Enabled' : 'Disabled',
            ];
        }
        if (array_key_exists('monday_deleted', $props) && $props['monday_deleted'] === false) {
            $rows[] = [
                'label' => 'Monday',
                'value' => 'Left unchanged',
            ];
        }
        if (array_key_exists('forge_cancelled', $props) && $props['forge_cancelled'] !== null) {
            $rows[] = [
                'label' => 'Forge',
                'value' => $props['forge_cancelled'] ? 'Booking cancelled' : 'No cancel needed',
            ];
        }
        if (! empty($props['changed_fields']) && is_array($props['changed_fields'])) {
            $rows[] = [
                'label' => 'Changed',
                'value' => implode(', ', array_map(
                    static fn ($field) => Str::headline(str_replace('_', ' ', (string) $field)),
                    $props['changed_fields']
                )),
            ];
        }

        return $rows;
    }

    public function subjectUrl(): ?string
    {
        $props = is_array($this->properties) ? $this->properties : [];
        $enquiryId = (int) ($props['enquiry_id'] ?? 0);
        if ($enquiryId < 1 && str_contains((string) $this->subject_type, 'Enquiry') && $this->subject_id) {
            $enquiryId = (int) $this->subject_id;
        }
        if ($enquiryId > 0 && $this->action !== 'delete') {
            return route('admin.enquiries.show', $enquiryId);
        }

        if (str_contains((string) $this->subject_type, 'TrainingMatrixEntry') || str_contains((string) ($this->route_name ?? ''), 'training-matrix')) {
            $entryId = (int) ($props['matrix_entry_id'] ?? $this->subject_id ?? 0);
            if ($entryId > 0 && $this->action !== 'delete') {
                return route('admin.training-matrix.index', ['modal' => 'edit', 'entry' => $entryId]);
            }

            return route('admin.training-matrix.index');
        }

        return null;
    }

    public function deviceSummary(): string
    {
        $ua = trim((string) ($this->user_agent ?? ''));
        if ($ua === '') {
            return '—';
        }

        $browser = 'Browser';
        if (str_contains($ua, 'Edg/')) {
            $browser = 'Edge';
        } elseif (str_contains($ua, 'Chrome/')) {
            $browser = 'Chrome';
        } elseif (str_contains($ua, 'Firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome/')) {
            $browser = 'Safari';
        }

        $os = 'device';
        if (str_contains($ua, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh')) {
            $os = 'macOS';
        } elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) {
            $os = 'iOS';
        } elseif (str_contains($ua, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($ua, 'Linux')) {
            $os = 'Linux';
        }

        return $browser.' · '.$os;
    }
}
