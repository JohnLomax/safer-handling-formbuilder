<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\EnquiryEvent;
use App\Services\ActivityLogger;
use App\Services\EnquiryProcessRetry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Throwable;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', 'all'));
        $tab = trim((string) $request->query('tab', 'live'));
        $perPage = (int) $request->query('per_page', 25);
        if (! in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        $allowedStatuses = ['all', 'in_progress', 'contacted', 'quote_sent', 'quote_accepted', 'quote_won', 'submitted', 'failed'];
        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        if (! in_array($tab, ['live', 'archive'], true)) {
            $tab = 'live';
        }

        // Live = last 30 days; Archive = older than 30 days (auto-moves out of Live).
        $archiveBefore = now()->subDays(30);

        $query = Enquiry::query()->withCount('events');
        $this->applyEnquiryTabFilter($query, $tab, $archiveBefore);

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

        $viewData = [
            'enquiries' => $enquiries,
            'search' => $search,
            'statusFilter' => $status,
            'tab' => $tab,
            'perPage' => $perPage,
            'tabCounts' => [
                'live' => Enquiry::query()->where('created_at', '>=', $archiveBefore)->count(),
                'archive' => Enquiry::query()->where('created_at', '<', $archiveBefore)->count(),
            ],
            'statusCounts' => $this->enquiryStatusCountsForTab($tab, $archiveBefore),
        ];

        if ($request->ajax() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.enquiries._index_panel', $viewData);
        }

        return view('admin.enquiries.index', $viewData);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Enquiry>  $query
     * @param  \Illuminate\Support\Carbon  $archiveBefore
     */
    private function applyEnquiryTabFilter($query, string $tab, $archiveBefore): void
    {
        if ($tab === 'archive') {
            $query->where('created_at', '<', $archiveBefore);

            return;
        }

        $query->where('created_at', '>=', $archiveBefore);
    }

    /**
     * @param  \Illuminate\Support\Carbon  $archiveBefore
     * @return array<string, int>
     */
    private function enquiryStatusCountsForTab(string $tab, $archiveBefore): array
    {
        $counts = ['all' => 0];
        foreach (['in_progress', 'contacted', 'quote_sent', 'quote_accepted', 'quote_won', 'submitted', 'failed'] as $status) {
            $query = Enquiry::query();
            $this->applyEnquiryTabFilter($query, $tab, $archiveBefore);
            $counts[$status] = $query->where('status', $status)->count();
        }

        $allQuery = Enquiry::query();
        $this->applyEnquiryTabFilter($allQuery, $tab, $archiveBefore);
        $counts['all'] = $allQuery->count();

        return $counts;
    }

    public function show(Enquiry $enquiry, EnquiryProcessRetry $retry): View
    {
        $enquiry->load(['events', 'voucher']);

        return view('admin.enquiries.show', [
            'enquiry' => $enquiry,
            'retryableActions' => $retry->retryableActions($enquiry),
            'canResendBookingEmail' => $retry->canResendBookingEmail($enquiry),
            'acceptVenueEmailBlockedReason' => $retry->acceptVenueEmailBlockedReason($enquiry),
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
            'preferredDate' => ['nullable', 'date_format:Y-m-d'],
            'dateNotSure' => ['nullable', 'boolean'],
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

        $root = dirname(base_path());
        require_once $root.'/config.php';
        require_once $root.'/enquiry_logger.php';
        require_once $root.'/monday_helpers.php';
        require_once $root.'/phone_helpers.php';

        $dateNotSure = $request->boolean('dateNotSure');
        $preferredDate = enquiryPreferredDateOnly((string) ($validated['preferredDate'] ?? ''));
        if ($dateNotSure) {
            $preferredDate = '';
        } elseif ($preferredDate === '') {
            return redirect()
                ->route('admin.enquiries.show', ['enquiry' => $enquiry, 'edit_booking' => 1])
                ->withErrors(['preferredDate' => 'Enter a preferred date, or tick “Not sure on date yet”.'])
                ->withInput()
                ->with('open_booking_edit', true);
        }

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
            'phone' => normalizePhoneForApi((string) $validated['phone']),
            'preferredDate' => $preferredDate,
            'dateNotSure' => $dateNotSure,
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
            'invoicePhone' => normalizePhoneForApi((string) ($validated['invoicePhone'] ?? '')),
            'purchaseOrderNumber' => trim((string) ($validated['purchaseOrderNumber'] ?? '')),
            'termsAccepted' => true,
            'source' => $enquiry->hasBookingDetails() ? 'admin_edit' : 'admin_manual',
        ];

        try {
            enquiryLoggerUpdatePreferredDate((int) $enquiry->id, $preferredDate, $dateNotSure);
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
                    'preferred_date' => $preferredDate,
                    'date_not_sure' => $dateNotSure,
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
                require_once $root.'/forge_webhook.php';
                if (! forgeExceptionAlreadyLogged($forgeError)) {
                    enquiryLoggerEvent(
                        (int) $enquiry->id,
                        'forge_booking_sync_failed',
                        forgeFailureReasonFromException($forgeError),
                        ['error' => $forgeError->getMessage(), 'trigger' => 'admin_booking_save']
                    );
                }
                $warnings[] = 'Forge booking sync failed: '.forgeFailureReasonFromException($forgeError);
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
            'Accept Quote / venue details email',
            fn () => $retry->resendBookingEmail($enquiry),
            'Resent Accept Quote / venue details email successfully.'
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
            $result = $retry->emailXeroInvoiceToCustomer($enquiry);
            $enquiry->refresh();

            if (! empty($result['already_sent'])) {
                $status = 'Invoice was already sent to the customer for this enquiry.';
                if (! empty($result['kajabi']['enrolled'])) {
                    $status .= ' Kajabi enrollment completed.';
                }

                return redirect()
                    ->route('admin.enquiries.show', $enquiry)
                    ->with('status', $status);
            }

            if (! empty($result['emailed']) || ! empty($result['processed'])) {
                $parts = ['Invoice emailed to the customer'
                    .(! empty($result['to_email']) ? ' ('.$result['to_email'].')' : '')
                    .'.'];
                if (! empty($result['monday_quote_won']['moved'])) {
                    $parts[] = 'Moved to Monday "'.($result['monday_quote_won']['groupName'] ?? 'Quote Won').'".';
                }
                if (! empty($result['monday_courses_ongoing']['itemId'])) {
                    $parts[] = (! empty($result['monday_courses_ongoing']['created']) ? 'Created' : 'Updated')
                        .' Client Booking Form (Courses Ongoing) record.';
                }
                if (! empty($result['kajabi']['enrolled'])) {
                    $parts[] = 'Enrolled in Kajabi'
                        .(isset($result['kajabi']['offer_title']) ? ' ('.$result['kajabi']['offer_title'].')' : '')
                        .'.';
                } elseif (! empty($result['kajabi']['error']) && empty($result['kajabi']['already_enrolled'])) {
                    $parts[] = 'Kajabi enrollment did not complete.';
                }
                if (! empty($result['kajabi']['monday']['moved'])) {
                    $parts[] = 'Moved to Monday "'
                        .($result['kajabi']['monday']['groupName'] ?? 'Exported')
                        .'".';
                }

                return redirect()
                    ->route('admin.enquiries.show', $enquiry)
                    ->with('status', implode(' ', $parts));
            }

            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->with('status', 'Invoice email did not complete.');
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->withErrors(['xero_invoice_sent' => 'Could not email invoice to the customer: '.$e->getMessage()]);
        }
    }

    public function retryKajabiEnroll(Enquiry $enquiry, EnquiryProcessRetry $retry): RedirectResponse
    {
        return $this->runRetry(
            $enquiry,
            'Kajabi enrollment',
            fn () => $retry->retryKajabiEnroll($enquiry),
            'Kajabi account/course enrollment completed successfully.'
        );
    }

    public function forgeResync(Enquiry $enquiry, EnquiryProcessRetry $retry): RedirectResponse
    {
        try {
            $wasSynced = $enquiry->forge_synced_at !== null;
            $result = $retry->forgeResync($enquiry);
            $enquiry->refresh();

            $stage = trim((string) ($result['booking_status'] ?? $enquiry->forge_booking_status ?? ''));
            $label = $stage !== '' ? str_replace('_', ' ', $stage) : 'booking snapshot';
            $verb = $wasSynced ? 'Resynced' : 'Synced';

            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->with('status', $verb.' to Forge as '.$label.'.');
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->withErrors(['forge' => 'Forge sync failed: '.$e->getMessage()]);
        }
    }

    public function forgeCancel(Enquiry $enquiry, EnquiryProcessRetry $retry): RedirectResponse
    {
        try {
            $retry->forgeCancel($enquiry);

            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->with('status', 'Forge booking marked as cancelled.');
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->withErrors(['forge' => 'Forge cancel failed: '.$e->getMessage()]);
        }
    }

    public function applyVoucher(Request $request, Enquiry $enquiry, EnquiryProcessRetry $retry): RedirectResponse
    {
        if ($enquiry->status === 'quote_won') {
            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->withErrors(['voucher' => 'Can\'t apply a voucher because this enquiry is Quote Won.']);
        }

        $root = dirname(base_path());
        require_once $root.'/voucher_helpers.php';

        $code = trim((string) $request->input('voucher_code', ''));
        $originalNet = $this->resolveEnquiryBaseQuoteNet($enquiry);

        if ($originalNet <= 0) {
            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->withErrors(['voucher' => 'This enquiry does not have a quote value to discount yet.']);
        }

        $result = voucherApplyToQuoteNet($originalNet, $code, (int) $enquiry->id);
        if (! $result['ok'] || $result['voucher'] === null) {
            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->withErrors(['voucher' => $result['error'] ?? 'That voucher could not be applied.']);
        }

        voucherAttachToEnquiry(
            (int) $enquiry->id,
            $result['voucher'],
            (float) $result['original'],
            (float) $result['final'],
            (float) $result['discount']
        );

        $enquiry->refresh();

        ActivityLogger::log(
            action: 'update',
            description: 'Applied voucher '.$result['voucher']['code'].' (−£'.number_format((float) $result['discount'], 2).') to enquiry #'.$enquiry->id,
            subjectType: Enquiry::class,
            subjectId: (int) $enquiry->id,
            properties: [
                'area' => 'Vouchers',
                'voucher_id' => $result['voucher']['id'],
                'voucher_code' => $result['voucher']['code'],
                'discount' => $result['discount'],
                'original' => $result['original'],
                'final' => $result['final'],
                'source' => 'admin',
            ],
        );

        $enquiry->events()->create([
            'event_type' => 'voucher_applied',
            'message' => 'Voucher '.$result['voucher']['code'].' applied by admin (−£'.number_format((float) $result['discount'], 2).').',
            'metadata' => [
                'voucher_id' => $result['voucher']['id'],
                'voucher_code' => $result['voucher']['code'],
                'discount' => $result['discount'],
                'source' => 'admin',
            ],
            'created_at' => now(),
        ]);

        $status = 'Voucher '.$result['voucher']['code'].' applied. Quote net is now £'.number_format((float) $result['final'], 2).'.';
        try {
            $regen = $retry->regenerateQuoteAfterVoucherChange($enquiry->fresh());
            if ($regen['regenerated']) {
                $status .= ' '.$regen['message'];
            } elseif ($regen['message'] !== '') {
                $status .= ' '.$regen['message'];
            }
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->with('status', $status)
                ->withErrors(['voucher' => 'Voucher saved, but the Xero quote could not be regenerated: '.$e->getMessage()]);
        }

        return redirect()
            ->route('admin.enquiries.show', $enquiry)
            ->with('status', $status);
    }

    public function removeVoucher(Enquiry $enquiry, EnquiryProcessRetry $retry): RedirectResponse
    {
        if ($enquiry->status === 'quote_won') {
            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->withErrors(['voucher' => 'Can\'t change a voucher because this enquiry is Quote Won.']);
        }

        $root = dirname(base_path());
        require_once $root.'/voucher_helpers.php';

        if (! $enquiry->hasVoucher()) {
            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->with('status', 'No voucher was applied to this enquiry.');
        }

        $code = (string) ($enquiry->voucher_code ?: 'voucher');
        voucherClearFromEnquiry((int) $enquiry->id);
        $enquiry->refresh();

        ActivityLogger::log(
            action: 'update',
            description: 'Removed voucher '.$code.' from enquiry #'.$enquiry->id,
            subjectType: Enquiry::class,
            subjectId: (int) $enquiry->id,
            properties: [
                'area' => 'Vouchers',
                'voucher_code' => $code,
                'source' => 'admin',
            ],
        );

        $enquiry->events()->create([
            'event_type' => 'voucher_removed',
            'message' => 'Voucher '.$code.' removed by admin.',
            'metadata' => [
                'voucher_code' => $code,
                'source' => 'admin',
            ],
            'created_at' => now(),
        ]);

        $status = 'Voucher removed. Quote restored to the original net.';
        try {
            $regen = $retry->regenerateQuoteAfterVoucherChange($enquiry->fresh());
            if ($regen['regenerated']) {
                $status .= ' '.$regen['message'];
            } elseif ($regen['message'] !== '') {
                $status .= ' '.$regen['message'];
            }
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->with('status', $status)
                ->withErrors(['voucher' => 'Voucher removed, but the Xero quote could not be regenerated: '.$e->getMessage()]);
        }

        return redirect()
            ->route('admin.enquiries.show', $enquiry)
            ->with('status', $status);
    }

    private function resolveEnquiryBaseQuoteNet(Enquiry $enquiry): float
    {
        if ($enquiry->quote_net_original !== null && (float) $enquiry->quote_net_original > 0) {
            return round((float) $enquiry->quote_net_original, 2);
        }

        $form = is_array($enquiry->form_data_json) ? $enquiry->form_data_json : [];
        foreach (['quoteValueOriginal', 'quoteValue'] as $key) {
            $raw = trim((string) ($form[$key] ?? ''));
            $raw = preg_replace('/[^0-9.\-]/', '', $raw) ?? '';
            if ($raw !== '' && is_numeric($raw) && (float) $raw > 0) {
                // If a voucher is already applied and we're reading quoteValue, prefer original.
                if ($key === 'quoteValue' && $enquiry->hasVoucher() && empty($form['quoteValueOriginal'])) {
                    $discount = (float) ($enquiry->voucher_amount ?? 0);
                    if ($discount > 0) {
                        return round((float) $raw + $discount, 2);
                    }
                }

                return round((float) $raw, 2);
            }
        }

        if (
            $enquiry->audience_type === 'organisation'
            || (
                $enquiry->audience_type === 'me'
                && in_array((string) $enquiry->personal_goal, ['bookCourse', ''], true)
            )
        ) {
            $root = dirname(base_path());
            require_once $root.'/training_matrix_helpers.php';
            try {
                $resolved = trainingMatrixResolveOrganisationSubmission([
                    'sector' => $enquiry->sector,
                    'orgCourse' => $enquiry->org_course,
                    'courseFormat' => $enquiry->course_format,
                    'formatSubOption' => $enquiry->format_sub_option,
                    'matrixAttendees' => $enquiry->matrix_attendees ?? data_get($form, 'matrixAttendees'),
                    'attendees' => $enquiry->attendees,
                    'organisationCompany' => $enquiry->organisation_company,
                    'preferredDateTime' => $enquiry->preferred_date_time,
                    'preferredDate2' => $enquiry->preferred_date_2,
                    'enableSecondDate' => $enquiry->enable_second_date ? '1' : '0',
                    'dateNotSure' => $enquiry->date_not_sure ? '1' : '0',
                    'extraNotes' => $enquiry->extra_notes,
                ]);
                $raw = preg_replace('/[^0-9.\-]/', '', (string) ($resolved['quoteValue'] ?? '')) ?? '';
                if ($raw !== '' && is_numeric($raw)) {
                    return round((float) $raw, 2);
                }
            } catch (Throwable) {
                // fall through
            }
        }

        return 0.0;
    }

    public function destroy(Enquiry $enquiry, EnquiryProcessRetry $retry): RedirectResponse
    {
        try {
            $result = $this->deleteEnquiryRecord($enquiry, $retry);
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.enquiries.show', $enquiry)
                ->withErrors([
                    'delete' => 'Could not cancel the Forge booking before deleting this enquiry: '.$e->getMessage(),
                ]);
        }

        return redirect()
            ->route('admin.enquiries.index')
            ->with('status', $result['status']);
    }

    public function bulkDestroy(Request $request, EnquiryProcessRetry $retry): RedirectResponse
    {
        $validated = $request->validate([
            'enquiry_ids' => ['required', 'array', 'min:1'],
            'enquiry_ids.*' => ['integer', 'distinct', 'exists:enquiries,id'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['enquiry_ids'])));
        $enquiries = Enquiry::query()->whereIn('id', $ids)->orderBy('id')->get();

        $deleted = 0;
        $forgeCancelled = 0;
        $failures = [];

        foreach ($enquiries as $enquiry) {
            try {
                $result = $this->deleteEnquiryRecord($enquiry, $retry);
                $deleted++;
                if ($result['forge_cancelled']) {
                    $forgeCancelled++;
                }
            } catch (Throwable $e) {
                $failures[] = '#'.$enquiry->id.': '.$e->getMessage();
            }
        }

        $parts = [];
        if ($deleted > 0) {
            $parts[] = $deleted === 1
                ? 'Deleted 1 enquiry from the system.'
                : 'Deleted '.$deleted.' enquiries from the system.';
            $parts[] = 'Monday enquiries were not deleted.';
            if ($forgeCancelled > 0) {
                $parts[] = $forgeCancelled === 1
                    ? '1 Forge booking was cancelled.'
                    : $forgeCancelled.' Forge bookings were cancelled.';
            }
        }

        $redirect = redirect()->route('admin.enquiries.index');

        if ($parts !== []) {
            $redirect->with('status', implode(' ', $parts));
        }

        if ($failures !== []) {
            $redirect->withErrors([
                'delete' => 'Could not delete '
                    .count($failures)
                    .(count($failures) === 1 ? ' enquiry' : ' enquiries')
                    .' (Forge cancel failed): '
                    .implode(' ', $failures),
            ]);
        }

        if ($deleted === 0 && $failures === []) {
            $redirect->withErrors(['delete' => 'No matching enquiries were found to delete.']);
        }

        return $redirect;
    }

    /**
     * @return array{status:string,forge_cancelled:bool}
     */
    private function deleteEnquiryRecord(Enquiry $enquiry, EnquiryProcessRetry $retry): array
    {
        $enquiryId = (int) $enquiry->id;
        $enquiryName = trim((string) $enquiry->name);
        $enquiryEmail = trim((string) $enquiry->email);
        $mondayItemId = trim((string) ($enquiry->monday_item_id ?? ''));
        $hadForgeBooking = $enquiry->forge_synced_at !== null
            && ! in_array(strtolower(trim((string) $enquiry->forge_booking_status)), ['cancelled', 'canceled'], true);

        $forgeCancelled = false;
        if ($retry->canForgeCancel($enquiry)) {
            $retry->forgeCancel($enquiry);
            $forgeCancelled = true;
        }

        $uploadDir = dirname(base_path()).'/data/booking-uploads/'.$enquiryId;
        if (is_dir($uploadDir)) {
            File::deleteDirectory($uploadDir);
        }

        $descriptionParts = [
            'Deleted enquiry #'.$enquiryId
                .($enquiryName !== '' ? ' ('.$enquiryName.')' : '')
                .' from the system.',
            'Monday enquiry was not deleted.',
        ];
        if ($forgeCancelled || $hadForgeBooking) {
            $descriptionParts[] = $forgeCancelled
                ? 'Forge booking was cancelled.'
                : 'No active Forge booking needed cancelling.';
        }

        ActivityLogger::log(
            action: 'delete',
            description: implode(' ', $descriptionParts),
            subjectType: Enquiry::class,
            subjectId: $enquiryId,
            properties: [
                'area' => 'Enquiry',
                'enquiry_id' => $enquiryId,
                'name' => $enquiryName !== '' ? $enquiryName : null,
                'email' => $enquiryEmail !== '' ? $enquiryEmail : null,
                'status' => $enquiry->status,
                'monday_item_id' => $mondayItemId !== '' ? $mondayItemId : null,
                'monday_deleted' => false,
                'forge_cancelled' => $forgeCancelled,
                'had_forge_booking' => $hadForgeBooking,
            ],
        );

        $enquiry->delete();

        return [
            'status' => implode(' ', $descriptionParts),
            'forge_cancelled' => $forgeCancelled,
        ];
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
            'booking_email' => 'Accept Quote / venue details email',
            'xero_invoice' => 'Xero draft invoice',
            'kajabi_enroll' => 'Kajabi enrollment',
            'forge_resync' => 'Forge sync',
            'forge_cancel' => 'Forge cancel',
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
