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
        'monday_synced_at',
        'xero_contact_id',
        'xero_quote_id',
        'xero_quote_number',
        'xero_invoice_id',
        'xero_invoice_number',
        'quote_email_sent_at',
        'xero_quote_sent_at',
        'xero_invoice_created_at',
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
     * Public form URL that restores this enquiry for viewing/editing.
     */
    public function formEditUrl(): string
    {
        $token = trim((string) $this->resume_token);
        if ($token === '') {
            $token = bin2hex(random_bytes(24));
            $this->forceFill(['resume_token' => $token])->save();
        }

        $base = rtrim(trim((string) Setting::getValue('form_base_url', '')), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }
        if ($base === '') {
            $base = rtrim(url('/'), '/');
        }

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

        $base = rtrim(trim((string) Setting::getValue('form_base_url', '')), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }
        if ($base === '') {
            $base = rtrim(url('/'), '/');
        }

        return $base.'/booking?'.http_build_query([
            'enquiry' => $this->id,
            'token' => $token,
        ]);
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
