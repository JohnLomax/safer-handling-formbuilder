<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\EnquiryEvent;
use App\Services\EnquiryProcessRetry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', 'all'));
        $perPage = (int) $request->query('per_page', 25);
        if (! in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        $allowedStatuses = ['all', 'in_progress', 'contacted', 'quote_sent', 'quote_accepted', 'quote_won', 'submitted', 'failed'];
        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $query = Enquiry::query()->withCount('events');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($search, $like): void {
                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }

                $builder
                    ->orWhere('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('enquiry_type', 'like', $like)
                    ->orWhere('sector', 'like', $like)
                    ->orWhere('org_course', 'like', $like)
                    ->orWhere('trainer_course_select', 'like', $like)
                    ->orWhere('monday_item_id', 'like', $like);
            });
        }

        $enquiries = $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.enquiries.index', [
            'enquiries' => $enquiries,
            'search' => $search,
            'statusFilter' => $status,
            'perPage' => $perPage,
            'statusCounts' => [
                'all' => Enquiry::query()->count(),
                'in_progress' => Enquiry::query()->where('status', 'in_progress')->count(),
                'contacted' => Enquiry::query()->where('status', 'contacted')->count(),
                'quote_sent' => Enquiry::query()->where('status', 'quote_sent')->count(),
                'quote_accepted' => Enquiry::query()->where('status', 'quote_accepted')->count(),
                'submitted' => Enquiry::query()->where('status', 'submitted')->count(),
                'failed' => Enquiry::query()->where('status', 'failed')->count(),
            ],
        ]);
    }

    public function show(Enquiry $enquiry, EnquiryProcessRetry $retry): View
    {
        $enquiry->load('events');

        return view('admin.enquiries.show', [
            'enquiry' => $enquiry,
            'retryableActions' => $retry->retryableActions($enquiry),
            'openBookingViewModal' => request()->boolean('view_booking'),
            'openBookingEditModal' => request()->boolean('edit_booking') || session('open_booking_edit', false),
        ]);
    }

    public function updateBooking(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $validated = $request->validate([
            'bookerName' => ['required', 'string', 'max:200'],
            'organisation' => ['nullable', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:200'],
            'phone' => ['required', 'string', 'max:40'],
            'venueAddress' => ['required', 'string', 'max:1000'],
            'studentNames' => ['nullable', 'string', 'max:5000'],
            'studentEmails' => ['nullable', 'string', 'max:5000'],
            'specialRequests' => ['nullable', 'string', 'max:2000'],
            'invoiceName' => ['required', 'string', 'max:200'],
            'invoiceEmail' => ['required', 'email', 'max:200'],
            'invoiceAddress' => ['required', 'string', 'max:1000'],
            'invoicePhone' => ['nullable', 'string', 'max:40'],
            'purchaseOrderNumber' => ['nullable', 'string', 'max:100'],
            'venueRequirements' => ['accepted'],
            'termsAccepted' => ['accepted'],
            'studentNamesFile' => ['nullable', 'file', 'max:8192'],
        ]);

        $studentNames = trim((string) ($validated['studentNames'] ?? ''));
        $studentEmails = trim((string) ($validated['studentEmails'] ?? ''));
        $nameLines = $studentNames === ''
            ? []
            : array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $studentNames) ?: [])));
        $emailLines = $studentEmails === ''
            ? []
            : array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $studentEmails) ?: [])));

        $hasUpload = $request->hasFile('studentNamesFile');
        $existing = is_array($enquiry->booking_details_json) ? $enquiry->booking_details_json : [];
        $existingFile = is_array($existing['studentNamesFile'] ?? null) ? $existing['studentNamesFile'] : null;

        if (count($nameLines) === 0 && count($emailLines) === 0 && ! $hasUpload && $existingFile === null) {
            return redirect()
                ->route('admin.enquiries.show', ['enquiry' => $enquiry, 'edit_booking' => 1])
                ->withErrors(['studentNames' => 'Provide student names and emails, or upload a delegate list.'])
                ->withInput()
                ->with('open_booking_edit', true);
        }

        if ((count($nameLines) > 0 || count($emailLines) > 0) && count($nameLines) !== count($emailLines)) {
            return redirect()
                ->route('admin.enquiries.show', ['enquiry' => $enquiry, 'edit_booking' => 1])
                ->withErrors(['studentEmails' => 'Student names and emails must have the same number of lines.'])
                ->withInput()
                ->with('open_booking_edit', true);
        }

        foreach ($emailLines as $delegateEmail) {
            if (! filter_var($delegateEmail, FILTER_VALIDATE_EMAIL)) {
                return redirect()
                    ->route('admin.enquiries.show', ['enquiry' => $enquiry, 'edit_booking' => 1])
                    ->withErrors(['studentEmails' => 'One or more student email addresses are invalid.'])
                    ->withInput()
                    ->with('open_booking_edit', true);
            }
        }

        $uploadedFileMeta = $existingFile;
        if ($hasUpload) {
            $file = $request->file('studentNamesFile');
            $originalName = $file->getClientOriginalName();
            $ext = strtolower($file->getClientOriginalExtension());
            $allowed = ['csv', 'xlsx', 'xls', 'txt', 'pdf', 'doc', 'docx'];
            if (! in_array($ext, $allowed, true)) {
                return redirect()
                    ->route('admin.enquiries.show', ['enquiry' => $enquiry, 'edit_booking' => 1])
                    ->withErrors(['studentNamesFile' => 'Unsupported file type.'])
                    ->withInput()
                    ->with('open_booking_edit', true);
            }

            $uploadDir = dirname(base_path()).'/data/booking-uploads/'.$enquiry->id;
            if (! is_dir($uploadDir) && ! mkdir($uploadDir, 0775, true) && ! is_dir($uploadDir)) {
                return redirect()
                    ->route('admin.enquiries.show', $enquiry)
                    ->withErrors(['booking' => 'Could not store the uploaded delegate file.']);
            }

            $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME)) ?: 'delegates';
            $storedName = $safeBase.'-'.bin2hex(random_bytes(4)).'.'.$ext;
            $file->move($uploadDir, $storedName);
            $uploadedFileMeta = [
                'originalName' => $originalName,
                'storedName' => $storedName,
                'relativePath' => 'data/booking-uploads/'.$enquiry->id.'/'.$storedName,
                'size' => (int) filesize($uploadDir.'/'.$storedName),
                'mime' => (string) ($file->getClientMimeType() ?? ''),
            ];
        }

        $delegates = [];
        for ($i = 0, $count = count($nameLines); $i < $count; $i++) {
            $delegates[] = [
                'name' => $nameLines[$i],
                'email' => $emailLines[$i],
            ];
        }

        $details = [
            'bookerName' => trim($validated['bookerName']),
            'organisation' => trim((string) ($validated['organisation'] ?? '')),
            'email' => trim($validated['email']),
            'phone' => trim($validated['phone']),
            'venueAddress' => trim($validated['venueAddress']),
            'studentNames' => $studentNames,
            'studentEmails' => $studentEmails,
            'delegates' => $delegates,
            'studentNamesFile' => $uploadedFileMeta,
            'specialRequests' => trim((string) ($validated['specialRequests'] ?? '')),
            'venueRequirementsConfirmed' => true,
            'invoiceName' => trim($validated['invoiceName']),
            'invoiceEmail' => trim($validated['invoiceEmail']),
            'invoiceAddress' => trim($validated['invoiceAddress']),
            'invoicePhone' => trim((string) ($validated['invoicePhone'] ?? '')),
            'purchaseOrderNumber' => trim((string) ($validated['purchaseOrderNumber'] ?? '')),
            'termsAccepted' => true,
            'source' => $enquiry->hasBookingDetails() ? 'admin_edit' : 'admin_manual',
        ];

        $root = dirname(base_path());
        require_once $root.'/enquiry_logger.php';
        require_once $root.'/monday_helpers.php';

        try {
            enquiryLoggerSaveBookingDetails((int) $enquiry->id, $details);
            enquiryLoggerMarkQuoteAccepted((int) $enquiry->id);
            enquiryLoggerEvent(
                (int) $enquiry->id,
                'booking_details_submitted',
                $details['source'] === 'admin_manual'
                    ? 'Staff manually completed booking details for the client.'
                    : 'Staff updated booking details for the enquiry.',
                [
                    'organisation' => $details['organisation'],
                    'delegate_count' => count($delegates),
                    'source' => $details['source'],
                    'status' => 'quote_accepted',
                ]
            );

            $warnings = [];
            try {
                mondaySyncBookingDetails((int) $enquiry->id, $details);
            } catch (Throwable $mondayError) {
                enquiryLoggerEvent(
                    (int) $enquiry->id,
                    'monday_booking_sync_failed',
                    'Booking details were saved, but Monday sync failed.',
                    ['error' => $mondayError->getMessage()]
                );
                $warnings[] = 'Monday sync failed: '.$mondayError->getMessage();
            }

            try {
                require_once $root.'/xero.php';
                xeroMaybeCreateDraftInvoiceAfterQuoteAccepted((int) $enquiry->id, $details);
            } catch (Throwable $xeroError) {
                $warnings[] = 'Draft Xero invoice could not be created: '.$xeroError->getMessage();
            }

            try {
                require_once $root.'/forge_webhook.php';
                forgeMaybeSyncBooking((int) $enquiry->id, $details);
            } catch (Throwable $forgeError) {
                $warnings[] = 'Forge booking sync failed: '.$forgeError->getMessage();
            }

            $enquiry->refresh();

            if ($warnings !== []) {
                return redirect()
                    ->route('admin.enquiries.show', $enquiry)
                    ->with('status', 'Booking details saved, but '.implode(' ', $warnings));
            }

            $status = 'Booking details saved and synced to Monday (Quote Accepted).';
            if (trim((string) $enquiry->xero_invoice_id) !== '') {
                $status .= ' Draft Xero invoice created (not sent).';
            }
            if (trim((string) ($enquiry->forge_synced_at ?? '')) !== '') {
                $status .= ' Forge booking snapshot queued for review.';
            }

            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->with('status', $status);
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.enquiries.show', ['enquiry' => $enquiry, 'edit_booking' => 1])
                ->withErrors(['booking' => 'Could not save booking details: '.$e->getMessage()])
                ->withInput()
                ->with('open_booking_edit', true);
        }
    }

    public function retryQuoteEmail(Enquiry $enquiry, EnquiryProcessRetry $retry): RedirectResponse
    {
        return $this->runRetry($enquiry, 'quote email', fn () => $retry->retryQuoteEmail($enquiry));
    }

    public function retryLeadNotification(Enquiry $enquiry, EnquiryProcessRetry $retry): RedirectResponse
    {
        return $this->runRetry($enquiry, 'lead notification', fn () => $retry->retryLeadNotification($enquiry));
    }

    public function retryResumeEmail(Enquiry $enquiry, EnquiryProcessRetry $retry): RedirectResponse
    {
        return $this->runRetry($enquiry, 'Edit Enquiry Email', fn () => $retry->retryResumeEmail($enquiry));
    }

    public function retryBookingEmail(Enquiry $enquiry, EnquiryProcessRetry $retry): RedirectResponse
    {
        return $this->runRetry($enquiry, 'booking details email', fn () => $retry->retryBookingEmail($enquiry));
    }

    public function resendResumeEmail(Enquiry $enquiry, EnquiryProcessRetry $retry): RedirectResponse
    {
        return $this->runRetry(
            $enquiry,
            'Edit Enquiry Email',
            fn () => $retry->resendResumeEmail($enquiry),
            'Resent Edit Enquiry Email successfully.'
        );
    }

    public function resendBookingEmail(Enquiry $enquiry, EnquiryProcessRetry $retry): RedirectResponse
    {
        return $this->runRetry(
            $enquiry,
            'accept terms email',
            fn () => $retry->resendBookingEmail($enquiry),
            'Resent accept terms email successfully.'
        );
    }

    public function retryXeroInvoice(Enquiry $enquiry, EnquiryProcessRetry $retry): RedirectResponse
    {
        return $this->runRetry(
            $enquiry,
            'Xero draft invoice',
            fn () => $retry->retryXeroInvoice($enquiry),
            'Created draft Xero invoice successfully (not sent).'
        );
    }

    public function syncXeroInvoiceSent(Enquiry $enquiry, EnquiryProcessRetry $retry): RedirectResponse
    {
        try {
            $result = $retry->syncXeroInvoiceSent($enquiry);
            $enquiry->refresh();

            if (! empty($result['already_sent']) || (! empty($result['sent']) && empty($result['processed']))) {
                return redirect()
                    ->route('admin.enquiries.show', $enquiry)
                    ->with('status', 'Xero invoice was already marked as sent for this enquiry.');
            }

            if (! empty($result['processed'])) {
                $parts = ['Xero invoice marked as sent.'];
                if (! empty($result['monday_quote_won']['moved'])) {
                    $parts[] = 'Moved to Monday "'.($result['monday_quote_won']['groupName'] ?? 'Quote Won').'".';
                }
                if (! empty($result['monday_courses_ongoing']['itemId'])) {
                    $parts[] = (! empty($result['monday_courses_ongoing']['created']) ? 'Created' : 'Updated')
                        .' Client Booking Form (Courses Ongoing) record.';
                }

                return redirect()
                    ->route('admin.enquiries.show', $enquiry)
                    ->with('status', implode(' ', $parts));
            }

            $status = $result['invoice_status'] ?? 'DRAFT';

            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->with('status', 'Xero invoice is not marked as sent yet (status: '.$status.'). Send/email it in Xero, then check again.');
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->withErrors(['xero_invoice_sent' => 'Could not sync Xero invoice sent status: '.$e->getMessage()]);
        }
    }

    public function retryEvent(Enquiry $enquiry, EnquiryEvent $event, EnquiryProcessRetry $retry): RedirectResponse
    {
        if ((int) $event->enquiry_id !== (int) $enquiry->id) {
            abort(404);
        }

        $labels = [
            'quote_email' => 'quote email',
            'lead_notification' => 'lead notification',
            'resume_email' => 'Edit Enquiry Email',
            'booking_email' => 'booking details email',
            'xero_invoice' => 'Xero draft invoice',
        ];

        try {
            $action = $retry->retryFromFailedEvent($enquiry, $event);
            $label = $labels[$action] ?? 'process';

            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->with('status', 'Retried '.$label.' successfully.');
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->withErrors(['retry' => 'Retry failed: '.$e->getMessage()]);
        }
    }

    private function runRetry(
        Enquiry $enquiry,
        string $label,
        callable $callback,
        ?string $successMessage = null
    ): RedirectResponse {
        try {
            $callback();

            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->with('status', $successMessage ?? ('Retried '.$label.' successfully.'));
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->withErrors(['retry' => 'Retry failed: '.$e->getMessage()]);
        }
    }
}
