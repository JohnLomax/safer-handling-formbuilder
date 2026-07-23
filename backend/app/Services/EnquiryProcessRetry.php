<?php

namespace App\Services;

use App\Models\Enquiry;
use App\Models\EnquiryEvent;
use RuntimeException;
use Throwable;

class EnquiryProcessRetry
{
    public function __construct()
    {
        $this->bootFormHelpers();
    }

    /**
     * @return list<string>
     */
    public function retryableActions(Enquiry $enquiry): array
    {
        $actions = [];

        if ($this->canRetryQuoteEmail($enquiry)) {
            $actions[] = 'quote_email';
        }

        if ($this->canRetryLeadNotification($enquiry)) {
            $actions[] = 'lead_notification';
        }

        if ($this->canRetryResumeEmail($enquiry)) {
            $actions[] = 'resume_email';
        }

        if ($this->canRetryBookingEmail($enquiry)) {
            $actions[] = 'booking_email';
        }

        if ($this->canRetryXeroInvoice($enquiry)) {
            $actions[] = 'xero_invoice';
        }

        if ($this->canSyncXeroInvoiceSent($enquiry)) {
            $actions[] = 'xero_invoice_sent';
        }

        return $actions;
    }

    public function canRetryQuoteEmail(Enquiry $enquiry): bool
    {
        return in_array($enquiry->status, ['submitted', 'contacted', 'quote_sent', 'quote_accepted'], true)
            && $enquiry->quote_email_sent_at === null
            && ! $this->hasSuccessfulEvent($enquiry, 'quote_email_sent')
            && ($this->isTrainerFlow($enquiry) || $this->isOrganisationTrainingFlow($enquiry));
    }

    public function canRetryLeadNotification(Enquiry $enquiry): bool
    {
        return in_array($enquiry->status, ['submitted', 'contacted', 'quote_sent', 'quote_accepted'], true)
            && ! $this->hasSuccessfulEvent($enquiry, 'lead_notification_sent')
            && ($this->isTrainerFlow($enquiry) || $this->isOrganisationTrainingFlow($enquiry));
    }

    public function canRetryResumeEmail(Enquiry $enquiry): bool
    {
        return trim((string) $enquiry->email) !== ''
            && trim((string) $enquiry->name) !== ''
            && $enquiry->resume_email_sent_at === null
            && ! $this->hasSuccessfulEvent($enquiry, 'resume_email_sent');
    }

    public function canRetryBookingEmail(Enquiry $enquiry): bool
    {
        // Booking-details email is disabled (quote email already links to the booking form).
        return false;
    }

    public function canResendResumeEmail(Enquiry $enquiry): bool
    {
        return trim((string) $enquiry->email) !== ''
            && trim((string) $enquiry->name) !== ''
            && brevoApiKey() !== '';
    }

    public function canResendBookingEmail(Enquiry $enquiry): bool
    {
        // Booking-details email is disabled (quote email already links to the booking form).
        return false;
    }

    public function canRetryXeroInvoice(Enquiry $enquiry): bool
    {
        return xeroEnabled()
            && trim((string) $enquiry->xero_quote_id) !== ''
            && trim((string) $enquiry->xero_invoice_id) === ''
            && (
                $enquiry->booking_submitted_at !== null
                || $enquiry->terms_accepted_at !== null
                || $enquiry->status === 'quote_accepted'
                || $enquiry->hasBookingDetails()
            );
    }

    public function canSyncXeroInvoiceSent(Enquiry $enquiry): bool
    {
        return xeroEnabled()
            && trim((string) $enquiry->xero_invoice_id) !== ''
            && $enquiry->xero_invoice_sent_at === null;
    }

    public function retryQuoteEmail(Enquiry $enquiry): void
    {
        if (! $this->canRetryQuoteEmail($enquiry)) {
            throw new RuntimeException('Quote email has already been sent, or cannot be sent for this enquiry.');
        }

        $quoteData = $this->buildQuoteEmailData($enquiry);
        $quoteData['enquiryId'] = (int) $enquiry->id;
        $quoteData['resumeToken'] = enquiryLoggerEnsureResumeToken((int) $enquiry->id);
        $quoteData['email'] = (string) $enquiry->email;

        try {
            $result = sendQuoteToClient((string) $enquiry->email, (string) $enquiry->name, $quoteData);
            $enquiry->forceFill(['quote_email_sent_at' => now()])->save();
            if (($result['channel'] ?? '') === 'xero') {
                $quote = $result['xero']['quote'] ?? [];
                $contact = $result['xero']['contact'] ?? [];
                $enquiry->forceFill([
                    'status' => 'quote_sent',
                    'xero_contact_id' => $contact['ContactID'] ?? $enquiry->xero_contact_id,
                    'xero_quote_id' => $quote['QuoteID'] ?? $enquiry->xero_quote_id,
                    'xero_quote_number' => $quote['QuoteNumber'] ?? $enquiry->xero_quote_number,
                    'xero_quote_sent_at' => now(),
                ])->save();
                $this->logEvent(
                    $enquiry,
                    'quote_email_sent',
                    'Xero quote created and emailed to the customer via Brevo (PDF attached).',
                    [
                        'retried' => true,
                        'channel' => 'xero',
                        'xero_quote_id' => $quote['QuoteID'] ?? null,
                        'xero_quote_number' => $quote['QuoteNumber'] ?? null,
                        'xero_contact_id' => $contact['ContactID'] ?? null,
                        'subtotal' => $quote['SubTotal'] ?? null,
                        'vat' => $quote['TotalTax'] ?? null,
                        'total' => $quote['Total'] ?? null,
                        'status' => 'quote_sent',
                    ]
                );

                try {
                    mondayMoveEnquiryToQuoteSentAfterXeroQuote((int) $enquiry->id);
                    $enquiry->unsetRelation('events');
                    $enquiry->refresh();
                } catch (Throwable $moveError) {
                    $this->logEvent(
                        $enquiry,
                        'monday_move_failed',
                        'Could not move enquiry to Monday group "Quote Sent" after Xero quote was emailed.',
                        ['retried' => true, 'error' => $moveError->getMessage()]
                    );
                }

                try {
                    maybeSendBookingDetailsEmail((int) $enquiry->id, (string) $enquiry->name, (string) $enquiry->email);
                    $enquiry->forceFill(['booking_email_sent_at' => now()])->save();
                    $enquiry->unsetRelation('events');
                    $enquiry->refresh();
                } catch (Throwable $bookingEmailError) {
                    $this->logEvent(
                        $enquiry,
                        'booking_email_failed',
                        'Booking details / terms acceptance email could not be sent.',
                        ['retried' => true, 'error' => $bookingEmailError->getMessage()]
                    );
                }
            } else {
                $enquiry->forceFill(['status' => 'contacted'])->save();
                $this->logEvent(
                    $enquiry,
                    'quote_email_sent',
                    'Quote confirmation email resent to the customer via Brevo.',
                    ['retried' => true, 'channel' => 'brevo', 'status' => 'contacted']
                );
            }
        } catch (Throwable $e) {
            $this->logEvent(
                $enquiry,
                'quote_email_failed',
                'Quote confirmation email could not be resent.',
                ['retried' => true, 'error' => $e->getMessage()]
            );

            throw $e;
        }
    }

    public function retryLeadNotification(Enquiry $enquiry): void
    {
        if (! $this->canRetryLeadNotification($enquiry)) {
            throw new RuntimeException('Lead notification has already been sent, or cannot be sent for this enquiry.');
        }

        $quoteData = $this->buildQuoteEmailData($enquiry);
        $post = $this->formPayload($enquiry);
        $leadData = buildNewLeadEmailData(
            (string) $enquiry->name,
            (string) $enquiry->email,
            (string) $enquiry->enquiry_type,
            (int) $enquiry->id,
            $this->isTrainerFlow($enquiry),
            $quoteData,
            $post
        );

        $officeEmail = brevoOfficeEmail();

        try {
            sendNewLeadNotificationViaBrevo($leadData);
            $this->logEvent(
                $enquiry,
                'lead_notification_sent',
                'New lead notification email resent to '.$officeEmail.' via Brevo.',
                ['retried' => true, 'office_email' => $officeEmail]
            );
        } catch (Throwable $e) {
            $this->logEvent(
                $enquiry,
                'lead_notification_failed',
                'New lead notification email to '.$officeEmail.' could not be resent.',
                [
                    'retried' => true,
                    'office_email' => $officeEmail,
                    'error' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    public function retryResumeEmail(Enquiry $enquiry): void
    {
        if (! $this->canRetryResumeEmail($enquiry)) {
            throw new RuntimeException('Edit Enquiry Email has already been sent, or cannot be sent for this enquiry.');
        }

        if (brevoApiKey() === '') {
            throw new RuntimeException('Brevo API key is not configured.');
        }

        $token = $enquiry->resume_token;
        if (! is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(16));
            $enquiry->forceFill(['resume_token' => $token])->save();
        }

        $resumeUrl = buildEnquiryResumeUrl((int) $enquiry->id, $token);
        if ($resumeUrl === '') {
            throw new RuntimeException('Form base URL is not configured.');
        }

        try {
            sendResumeEnquiryEmailViaBrevo((string) $enquiry->email, (string) $enquiry->name, [
                'name' => (string) $enquiry->name,
                'email' => (string) $enquiry->email,
                'enquiryType' => (string) $enquiry->enquiry_type,
                'resumeUrl' => $resumeUrl,
            ]);
            $enquiry->forceFill(['resume_email_sent_at' => now()])->save();
            $this->logEvent(
                $enquiry,
                'resume_email_sent',
                'Edit Enquiry Email resent so the customer can return to their saved form.',
                ['retried' => true]
            );

            try {
                mondayMoveEnquiryToBeingContactedAfterEditEmail((int) $enquiry->id);
                $enquiry->unsetRelation('events');
            } catch (Throwable $moveError) {
                $this->logEvent(
                    $enquiry,
                    'monday_move_failed',
                    'Could not move enquiry to Monday group "Being Contacted" after Edit Enquiry Email was sent.',
                    [
                        'retried' => true,
                        'monday_item_id' => $enquiry->monday_item_id,
                        'error' => $moveError->getMessage(),
                    ]
                );
            }
        } catch (Throwable $e) {
            $this->logEvent(
                $enquiry,
                'resume_email_failed',
                'Edit Enquiry Email could not be resent.',
                ['retried' => true, 'error' => $e->getMessage()]
            );

            throw $e;
        }
    }

    public function retryBookingEmail(Enquiry $enquiry): void
    {
        if (! $this->canRetryBookingEmail($enquiry)) {
            throw new RuntimeException('Booking details email has already been sent, or cannot be sent for this enquiry.');
        }

        try {
            $sent = maybeSendBookingDetailsEmail(
                (int) $enquiry->id,
                (string) $enquiry->name,
                (string) $enquiry->email
            );
            if (! $sent) {
                throw new RuntimeException('Booking details email could not be sent.');
            }
            $enquiry->forceFill(['booking_email_sent_at' => now()])->save();
            $enquiry->unsetRelation('events');
            $enquiry->refresh();
        } catch (Throwable $e) {
            $this->logEvent(
                $enquiry,
                'booking_email_failed',
                'Booking details / terms acceptance email could not be resent.',
                ['retried' => true, 'error' => $e->getMessage()]
            );

            throw $e;
        }
    }

    public function resendResumeEmail(Enquiry $enquiry): void
    {
        if (! $this->canResendResumeEmail($enquiry)) {
            throw new RuntimeException('Edit Enquiry Email cannot be resent for this enquiry.');
        }

        try {
            $sent = maybeSendResumeEnquiryEmail(
                (int) $enquiry->id,
                (string) $enquiry->name,
                (string) $enquiry->email,
                (string) $enquiry->enquiry_type,
                true
            );
            if (! $sent) {
                throw new RuntimeException('Edit Enquiry Email could not be resent.');
            }
            $enquiry->forceFill(['resume_email_sent_at' => now()])->save();
            $enquiry->unsetRelation('events');
            $enquiry->refresh();
        } catch (Throwable $e) {
            $this->logEvent(
                $enquiry,
                'resume_email_failed',
                'Edit Enquiry Email could not be resent.',
                ['resent' => true, 'error' => $e->getMessage()]
            );

            throw $e;
        }
    }

    public function resendBookingEmail(Enquiry $enquiry): void
    {
        if (! $this->canResendBookingEmail($enquiry)) {
            throw new RuntimeException('Accept terms email cannot be resent for this enquiry.');
        }

        try {
            $sent = maybeSendBookingDetailsEmail(
                (int) $enquiry->id,
                (string) $enquiry->name,
                (string) $enquiry->email,
                true
            );
            if (! $sent) {
                throw new RuntimeException('Accept terms email could not be resent.');
            }
            $enquiry->forceFill(['booking_email_sent_at' => now()])->save();
            $enquiry->unsetRelation('events');
            $enquiry->refresh();
        } catch (Throwable $e) {
            $this->logEvent(
                $enquiry,
                'booking_email_failed',
                'Booking details / terms acceptance email could not be resent.',
                ['resent' => true, 'error' => $e->getMessage()]
            );

            throw $e;
        }
    }

    public function retryXeroInvoice(Enquiry $enquiry): void
    {
        if (! $this->canRetryXeroInvoice($enquiry)) {
            throw new RuntimeException('A Xero draft invoice cannot be created for this enquiry.');
        }

        $details = is_array($enquiry->booking_details_json) ? $enquiry->booking_details_json : [];

        $invoice = xeroMaybeCreateDraftInvoiceAfterQuoteAccepted((int) $enquiry->id, $details);
        $enquiry->unsetRelation('events');
        $enquiry->refresh();

        if ($invoice === null && trim((string) $enquiry->xero_invoice_id) === '') {
            throw new RuntimeException('Xero draft invoice was not created.');
        }
    }

    /**
     * @return array{processed:bool,sent:bool,already_sent:bool,invoice_status:?string,monday_quote_won:?array,monday_courses_ongoing:?array}
     */
    public function syncXeroInvoiceSent(Enquiry $enquiry): array
    {
        if (! $this->canSyncXeroInvoiceSent($enquiry) && $enquiry->xero_invoice_sent_at === null) {
            throw new RuntimeException('This enquiry has no Xero invoice to check.');
        }

        $result = xeroMaybeProcessInvoiceSent((int) $enquiry->id);
        $enquiry->unsetRelation('events');
        $enquiry->refresh();

        if ($result === null) {
            throw new RuntimeException('Could not check Xero invoice status for this enquiry.');
        }

        return $result;
    }

    public function retryFromFailedEvent(Enquiry $enquiry, EnquiryEvent $event): string
    {
        $processKey = $event->processKey();
        if ($processKey !== null) {
            $successType = $processKey === 'xero_invoice'
                ? 'xero_invoice_created'
                : $processKey.'_sent';
            if ($this->hasSuccessfulEvent($enquiry, $successType)) {
                throw new RuntimeException('This step has already completed successfully.');
            }
        }

        return match ($event->event_type) {
            'quote_email_failed' => tap('quote_email', fn () => $this->retryQuoteEmail($enquiry)),
            'lead_notification_failed' => tap('lead_notification', fn () => $this->retryLeadNotification($enquiry)),
            'resume_email_failed' => tap('resume_email', fn () => $this->retryResumeEmail($enquiry)),
            'booking_email_failed' => tap('booking_email', fn () => $this->retryBookingEmail($enquiry)),
            'xero_invoice_failed' => tap('xero_invoice', fn () => $this->retryXeroInvoice($enquiry)),
            default => throw new RuntimeException('This journey step cannot be retried.'),
        };
    }

    private function hasSuccessfulEvent(Enquiry $enquiry, string $eventType): bool
    {
        if ($enquiry->relationLoaded('events')) {
            return $enquiry->events->contains(
                fn (EnquiryEvent $event): bool => $event->event_type === $eventType
            );
        }

        return $enquiry->events()
            ->where('event_type', $eventType)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQuoteEmailData(Enquiry $enquiry): array
    {
        $post = $this->formPayload($enquiry);

        return buildQuoteEmailDataFromSubmission($post, (string) $enquiry->name, (string) $enquiry->email);
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(Enquiry $enquiry): array
    {
        $payload = is_array($enquiry->form_data_json) ? $enquiry->form_data_json : [];

        $payload['name'] = $payload['name'] ?? $enquiry->name;
        $payload['email'] = $payload['email'] ?? $enquiry->email;
        $payload['enquiryType'] = $payload['enquiryType'] ?? $enquiry->enquiry_type;
        $payload['audienceType'] = $payload['audienceType'] ?? $enquiry->audience_type;
        $payload['personalGoal'] = $payload['personalGoal'] ?? $enquiry->personal_goal;
        $payload['trainerCourseSelect'] = $payload['trainerCourseSelect'] ?? $enquiry->trainer_course_select;
        $payload['trainerAttendees'] = $payload['trainerAttendees'] ?? $enquiry->trainer_attendees;
        $payload['sector'] = $payload['sector'] ?? $enquiry->sector;
        $payload['orgCourse'] = $payload['orgCourse'] ?? $enquiry->org_course;
        $payload['courseFormat'] = $payload['courseFormat'] ?? $enquiry->course_format;
        $payload['formatSubOption'] = $payload['formatSubOption'] ?? $enquiry->format_sub_option;
        $payload['matrixAttendees'] = $payload['matrixAttendees'] ?? $enquiry->matrix_attendees;
        $payload['organisationCompany'] = $payload['organisationCompany'] ?? $enquiry->organisation_company;
        $payload['attendees'] = $payload['attendees'] ?? $enquiry->attendees;
        $payload['extraNotes'] = $payload['extraNotes'] ?? $enquiry->extra_notes;
        if (! isset($payload['quoteValue']) || trim((string) $payload['quoteValue']) === '') {
            // Keep any stored quote value from the original submission payload.
            $payload['quoteValue'] = $payload['quoteValue'] ?? '';
        }

        // Enquiry columns are the source of truth for preferred date. Stale
        // form_data_json (e.g. leftover dateNotSure) was causing quote emails to
        // show a different date — or none — vs admin.
        unset($payload['dateNotSure'], $payload['preferredDateTime'], $payload['preferredDate'], $payload['preferredTime']);
        if ($enquiry->date_not_sure) {
            $payload['dateNotSure'] = 'on';
        } elseif (filled($enquiry->preferred_date_time)) {
            $payload['preferredDateTime'] = $enquiry->preferred_date_time;
            $payload['preferredDate'] = $enquiry->preferred_date_time;
        }

        if ($enquiry->booking_via_company && ! isset($payload['bookingViaCompany'])) {
            $payload['bookingViaCompany'] = '1';
        }

        return $payload;
    }

    private function isTrainerFlow(Enquiry $enquiry): bool
    {
        return $enquiry->audience_type === 'me' && $enquiry->personal_goal === 'becomeTrainer';
    }

    private function isOrganisationTrainingFlow(Enquiry $enquiry): bool
    {
        return $enquiry->enquiry_type === 'training' && $enquiry->audience_type === 'organisation';
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function logEvent(Enquiry $enquiry, string $eventType, string $message, ?array $metadata = null): void
    {
        EnquiryEvent::query()->create([
            'enquiry_id' => $enquiry->id,
            'event_type' => $eventType,
            'message' => $message,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function bootFormHelpers(): void
    {
        static $booted = false;
        if ($booted) {
            return;
        }

        $root = dirname(base_path());
        require_once $root.'/config.php';
        require_once $root.'/enquiry_logger.php';
        require_once $root.'/monday_helpers.php';
        require_once $root.'/brevo_email.php';
        require_once $root.'/xero.php';
        $booted = true;
    }
}
