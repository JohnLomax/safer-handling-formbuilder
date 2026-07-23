<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'enquiry_id',
        'event_type',
        'message',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @var array<string, string>
     */
    public const LABELS = [
        'details_entered' => 'Initial details entered',
        'details_updated' => 'Enquiry details updated',
        'monday_item_created' => 'Data entered into Monday',
        'monday_item_exists' => 'Existing Monday record found',
        'monday_fields_updated' => 'Monday fields updated',
        'monday_submission_updated' => 'Final details synced to Monday',
        'monday_moved_being_contacted' => 'Moved to Being Contacted on Monday',
        'monday_moved_quote_sent' => 'Moved to Quote Sent on Monday',
        'monday_moved_quote_accepted' => 'Moved to Quote Accepted on Monday',
        'monday_moved_quote_won' => 'Moved to Quote Won on Monday',
        'monday_quote_won_group_created' => 'Monday Quote Won group created',
        'monday_courses_ongoing_created' => 'Client Booking Form record created',
        'monday_courses_ongoing_synced' => 'Client Booking Form record updated',
        'monday_courses_ongoing_failed' => 'Client Booking Form sync failed',
        'monday_courses_ongoing_group_created' => 'Monday Courses Ongoing group created',
        'monday_courses_ongoing_recreated' => 'Client Booking Form record recreated',
        'monday_move_failed' => 'Monday move failed',
        'monday_move_skipped' => 'Monday move skipped',
        'form_submitted' => 'Enquiry form submitted',
        'quote_email_sent' => 'Xero Quote',
        'quote_email_failed' => 'Xero Quote failed',
        'quote_email_skipped' => 'Xero Quote skipped',
        'resume_email_sent' => 'Edit Enquiry Email sent',
        'resume_email_failed' => 'Edit Enquiry Email failed',
        'booking_email_sent' => 'Accept Quote / venue details email sent',
        'booking_email_failed' => 'Accept Quote / venue details email failed',
        'booking_details_submitted' => 'Booking details submitted',
        'monday_booking_group_created' => 'Monday booking group created',
        'monday_booking_synced' => 'Booking synced to Monday',
        'monday_booking_sync_failed' => 'Monday booking sync failed',
        'xero_invoice_created' => 'Draft Xero invoice created',
        'xero_invoice_sent' => 'Xero invoice sent',
        'xero_invoice_failed' => 'Xero invoice creation failed',
        'xero_invoice_skipped' => 'Xero invoice skipped',
        'xero_invoice_sent_check_failed' => 'Xero invoice sent check failed',
        'forge_booking_synced' => 'Forge booking snapshot sent',
        'forge_booking_sync_failed' => 'Forge booking sync failed',
        'forge_booking_sync_skipped' => 'Forge booking sync skipped',
        'forge_status_updated' => 'Forge booking status updated',
        'lead_notification_sent' => 'New lead email sent to office',
        'lead_notification_failed' => 'New lead email failed',
        'storage_saved' => 'Enquiry saved locally',
        'storage_failed' => 'Local save failed',
    ];

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->event_type] ?? ucfirst(str_replace('_', ' ', $this->event_type));
    }

    public function isMondayEvent(): bool
    {
        return str_starts_with($this->event_type, 'monday_');
    }

    public function isXeroEvent(): bool
    {
        if (str_starts_with($this->event_type, 'quote_email_')
            || str_starts_with($this->event_type, 'xero_')) {
            return true;
        }

        $metadata = is_array($this->metadata) ? $this->metadata : [];

        if (($metadata['channel'] ?? null) === 'xero') {
            return true;
        }

        foreach (['xero_quote_id', 'xero_quote_number', 'xero_contact_id', 'xero_invoice_id', 'xero_invoice_number'] as $key) {
            if (! empty($metadata[$key])) {
                return true;
            }
        }

        $haystack = strtolower($this->event_type.' '.$this->message);

        return str_contains($haystack, 'xero');
    }

    public function isEmailEvent(): bool
    {
        if ($this->isXeroEvent()) {
            return false;
        }

        return str_starts_with($this->event_type, 'quote_email_')
            || str_starts_with($this->event_type, 'resume_email_')
            || str_starts_with($this->event_type, 'booking_email_')
            || str_starts_with($this->event_type, 'lead_notification_');
    }

    public function isFailed(): bool
    {
        return str_ends_with($this->event_type, '_failed');
    }

    public function isSuccessful(): bool
    {
        return str_ends_with($this->event_type, '_sent')
            || $this->event_type === 'xero_invoice_created'
            || $this->event_type === 'monday_moved_quote_won'
            || $this->event_type === 'monday_courses_ongoing_created'
            || $this->event_type === 'monday_courses_ongoing_synced';
    }

    public function isManualRetrySuccess(): bool
    {
        return $this->isSuccessful() && ! empty($this->metadata['retried']);
    }

    public function processKey(): ?string
    {
        return match (true) {
            str_starts_with($this->event_type, 'quote_email_') => 'quote_email',
            str_starts_with($this->event_type, 'lead_notification_') => 'lead_notification',
            str_starts_with($this->event_type, 'resume_email_') => 'resume_email',
            str_starts_with($this->event_type, 'booking_email_') => 'booking_email',
            str_starts_with($this->event_type, 'xero_invoice_') => 'xero_invoice',
            default => null,
        };
    }

    public function isRetryable(): bool
    {
        return in_array($this->event_type, [
            'quote_email_failed',
            'lead_notification_failed',
            'resume_email_failed',
            'booking_email_failed',
            'xero_invoice_failed',
        ], true);
    }
}
