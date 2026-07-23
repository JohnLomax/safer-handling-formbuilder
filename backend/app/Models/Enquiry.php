<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enquiry extends Model
{
    protected $fillable = [
        'name',
        'email',
        'enquiry_type',
        'status',
        'audience_type',
        'personal_goal',
        'trainer_course_select',
        'booking_via_company',
        'trainer_attendees',
        'sector',
        'org_course',
        'course_format',
        'format_sub_option',
        'matrix_attendees',
        'organisation_company',
        'preferred_date_time',
        'date_not_sure',
        'attendees',
        'extra_notes',
        'form_data_json',
        'booking_details_json',
        'monday_item_id',
        'monday_booking_item_id',
        'monday_synced_at',
        'xero_contact_id',
        'xero_quote_id',
        'xero_quote_number',
        'xero_invoice_id',
        'xero_invoice_number',
        'quote_email_sent_at',
        'xero_quote_sent_at',
        'xero_invoice_created_at',
        'xero_invoice_sent_at',
        'forge_synced_at',
        'forge_event_id',
        'forge_last_action',
        'forge_booking_status',
        'booking_email_sent_at',
        'booking_submitted_at',
        'terms_accepted_at',
        'resume_token',
        'resume_email_sent_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'booking_via_company' => 'boolean',
            'date_not_sure' => 'boolean',
            'form_data_json' => 'array',
            'booking_details_json' => 'array',
            'monday_synced_at' => 'datetime',
            'quote_email_sent_at' => 'datetime',
            'xero_quote_sent_at' => 'datetime',
            'xero_invoice_created_at' => 'datetime',
            'xero_invoice_sent_at' => 'datetime',
            'forge_synced_at' => 'datetime',
            'booking_email_sent_at' => 'datetime',
            'booking_submitted_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'resume_email_sent_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(EnquiryEvent::class)->orderBy('created_at');
    }

    public function isMondaySynced(): bool
    {
        return $this->monday_synced_at !== null;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'submitted' => 'Submitted',
            'in_progress' => 'In progress',
            'contacted' => 'Contacted',
            'quote_sent' => 'Quote Sent',
            'quote_accepted' => 'Quote Accepted',
            'quote_won' => 'Quote Won',
            'failed' => 'Failed',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function enquiryTypeLabel(): string
    {
        return match ($this->enquiry_type) {
            'training' => 'Training',
            'equipment' => 'Equipment',
            'guidance' => 'Guidance',
            default => ucfirst($this->enquiry_type),
        };
    }

    /**
     * Human-readable preferred date for admin display.
     */
    public function preferredDateTimeLabel(): string
    {
        if ($this->date_not_sure) {
            return 'Not sure yet';
        }

        $raw = trim((string) $this->preferred_date_time);
        if ($raw === '') {
            return '';
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return $raw;
        }

        $dateOnly = substr(str_replace(' ', 'T', $raw), 0, 10);
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateOnly, new \DateTimeZone('Europe/London'));
        if ($dt === false || $dt->format('Y-m-d') !== $dateOnly) {
            return $raw;
        }

        return $dt->format('l j F Y');
    }

    /**
     * Public form URL that restores this enquiry for viewing/editing.
     */
    public function formEditUrl(): string
    {
        $token = trim((string) $this->resume_token);
        if ($token === '') {
            $token = bin2hex(random_bytes(24));
            $this->forceFill(['resume_token' => $token])->save();
        }

        $base = $this->publicFormBaseUrl();

        return $base.'/enquiry?'.http_build_query([
            'enquiry' => $this->id,
            'token' => $token,
        ]);
    }

    /**
     * Public booking details form URL linked to this enquiry.
     */
    public function bookingFormUrl(): string
    {
        $token = trim((string) $this->resume_token);
        if ($token === '') {
            $token = bin2hex(random_bytes(24));
            $this->forceFill(['resume_token' => $token])->save();
        }

        $base = $this->publicFormBaseUrl();

        return $base.'/booking?'.http_build_query([
            'enquiry' => $this->id,
            'token' => $token,
        ]);
    }

    private function publicFormBaseUrl(): string
    {
        $candidates = [
            rtrim(trim((string) Setting::getValue('form_base_url', '')), '/'),
            rtrim(trim((string) config('app.url')), '/'),
            rtrim(url('/'), '/'),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $host = strtolower((string) (parse_url($candidate, PHP_URL_HOST) ?: ''));
            if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                continue;
            }
            if (str_ends_with($host, '.sslip.io') || str_ends_with($host, '.nip.io')) {
                continue;
            }

            return $candidate;
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    public function hasBookingDetails(): bool
    {
        return $this->booking_submitted_at !== null && is_array($this->booking_details_json);
    }

    public function canStaffProgressBooking(): bool
    {
        return $this->booking_email_sent_at !== null
            || in_array($this->status, ['quote_sent', 'quote_accepted'], true)
            || $this->hasBookingDetails();
    }
}
