<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\EnquiryEvent;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class EnquiryAttendeesController extends Controller
{
    private const DUPLICATE_WINDOW_SECONDS = 90;

    /**
     * Add attendees to an enquiry, log the timeline event, and create a Xero
     * invoice charging the configured per-attendee rate (default £80 ex VAT).
     *
     * POST /api/enquiries/{enquiry}/attendees
     * Auth: Bearer / X-Api-Token / ?token=  (setting: enquiry_api_token)
     *
     * Body JSON:
     *   count (int, required)  — number of new attendees to add
     *   source (string)        — system name shown in the timeline, e.g. "Forge"
     *   email_invoice (bool)   — email the new invoice PDF (default true)
     *   idempotency_key (string) — optional; same key returns the first result
     *
     * Header:
     *   Idempotency-Key — preferred over body idempotency_key
     */
    public function store(Request $request, Enquiry $enquiry): JsonResponse
    {
        if (! $this->tokenIsValid($request)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:500'],
            'source' => ['nullable', 'string', 'max:120'],
            'email_invoice' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $count = (int) $validated['count'];
        $source = trim((string) ($validated['source'] ?? ''));
        if ($source === '') {
            $source = 'API';
        }
        $emailInvoice = array_key_exists('email_invoice', $validated)
            ? (bool) $validated['email_invoice']
            : true;
        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = trim((string) ($validated['idempotency_key'] ?? ''));
        }
        if (strlen($idempotencyKey) > 120) {
            $idempotencyKey = substr($idempotencyKey, 0, 120);
        }

        $lock = Cache::lock('enquiry-attendees-'.$enquiry->id, 30);

        try {
            return $lock->block(15, function () use ($enquiry, $count, $source, $emailInvoice, $idempotencyKey): JsonResponse {
                $enquiry->refresh();

                if ($idempotencyKey !== '') {
                    $prior = $this->findIdempotentResult((int) $enquiry->id, $idempotencyKey);
                    if ($prior !== null) {
                        return response()->json($prior + [
                            'ok' => true,
                            'duplicate' => true,
                            'idempotent_replay' => true,
                        ], 200);
                    }
                }

                $recent = $this->findRecentDuplicateAdd((int) $enquiry->id, $count, $source);
                if ($recent !== null) {
                    $payload = is_array($recent->metadata['api_response'] ?? null)
                        ? $recent->metadata['api_response']
                        : [
                            'enquiry_id' => (int) $enquiry->id,
                            'source' => $source,
                            'previous_attendees' => (int) ($recent->metadata['previous_attendees'] ?? 0),
                            'added' => $count,
                            'attendees' => (int) ($recent->metadata['attendees'] ?? $enquiry->attendees),
                            'attendee_field' => (string) ($recent->metadata['attendee_field'] ?? 'attendees'),
                        ];

                    return response()->json([
                        'ok' => false,
                        'error' => 'Duplicate attendees request ignored (same count/source within '
                            .self::DUPLICATE_WINDOW_SECONDS.' seconds).',
                        'duplicate' => true,
                        'enquiry_id' => (int) $enquiry->id,
                        'source' => $source,
                        'added' => $count,
                        'attendees' => (int) ($payload['attendees'] ?? $enquiry->attendees),
                        'previous_result' => $payload,
                    ], 409);
                }

                return $this->processAdd($enquiry, $count, $source, $emailInvoice, $idempotencyKey);
            });
        } catch (Throwable $e) {
            if (str_contains(strtolower($e->getMessage()), 'lock')) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Another attendees request is already in progress for this enquiry. Retry shortly.',
                    'duplicate' => true,
                ], 409);
            }

            throw $e;
        }
    }

    private function processAdd(
        Enquiry $enquiry,
        int $count,
        string $source,
        bool $emailInvoice,
        string $idempotencyKey
    ): JsonResponse {
        // matrix_attendees / trainer_attendees stay as the original quote headcount.
        // attendees is the running total (quote + API additions).
        $quoteAttendees = $enquiry->quoteAttendeeCount();
        $previous = max(
            max(0, (int) ($enquiry->attendees ?? 0)),
            $quoteAttendees
        );
        $newTotal = $previous + $count;

        $enquiry->attendees = $newTotal;
        $enquiry->save();

        $sourceLabel = $source;
        $message = $count === 1
            ? "1 new attendee from {$sourceLabel}"
            : "{$count} new attendees from {$sourceLabel}";

        $this->bootFormHelpers();

        $unitAmount = function_exists('xeroAdditionalAttendeeUnitAmount')
            ? xeroAdditionalAttendeeUnitAmount()
            : 80.0;

        $response = [
            'ok' => true,
            'enquiry_id' => (int) $enquiry->id,
            'source' => $source,
            'previous_attendees' => $previous,
            'added' => $count,
            'attendees' => $newTotal,
            'quote_attendees' => $quoteAttendees > 0 ? $quoteAttendees : $previous,
            'attendee_field' => 'attendees',
            'unit_amount' => $unitAmount,
            'net_total' => round($unitAmount * $count, 2),
            'invoice' => null,
            'emailed' => false,
            'to_email' => null,
            'invoice_error' => null,
            'email_error' => null,
            'duplicate' => false,
        ];
        if ($idempotencyKey !== '') {
            $response['idempotency_key'] = $idempotencyKey;
        }

        $addedEvent = EnquiryEvent::query()->create([
            'enquiry_id' => $enquiry->id,
            'event_type' => 'attendees_added',
            'message' => $message,
            'metadata' => [
                'source' => $source,
                'delta' => $count,
                'previous_attendees' => $previous,
                'attendees' => $newTotal,
                'quote_attendees' => $quoteAttendees > 0 ? $quoteAttendees : $previous,
                'attendee_field' => 'attendees',
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
            ],
            'created_at' => now(),
        ]);

        try {
            $invoice = xeroCreateAdditionalAttendeesInvoice((int) $enquiry->id, $count, $source);
            $response['invoice'] = [
                'id' => $invoice['InvoiceID'],
                'number' => $invoice['InvoiceNumber'],
                'status' => $invoice['Status'],
                'subtotal' => $invoice['SubTotal'],
                'vat' => $invoice['TotalTax'],
                'total' => $invoice['Total'],
                'unit_amount' => $invoice['UnitAmount'],
                'quantity' => $invoice['Quantity'],
            ];
            $response['unit_amount'] = $invoice['UnitAmount'];
            $response['net_total'] = $invoice['SubTotal'];

            EnquiryEvent::query()->create([
                'enquiry_id' => $enquiry->id,
                'event_type' => 'xero_additional_invoice_created',
                'message' => $count === 1
                    ? 'Additional attendees invoice created in Xero (1 attendee @ £'
                        .number_format((float) $invoice['UnitAmount'], 2).').'
                    : 'Additional attendees invoice created in Xero ('
                        .$count.' attendees @ £'
                        .number_format((float) $invoice['UnitAmount'], 2).' each).',
                'metadata' => [
                    'channel' => 'xero',
                    'source' => $source,
                    'delta' => $count,
                    'unit_amount' => $invoice['UnitAmount'],
                    'xero_invoice_id' => $invoice['InvoiceID'],
                    'xero_invoice_number' => $invoice['InvoiceNumber'] !== '' ? $invoice['InvoiceNumber'] : null,
                    'xero_invoice_status' => $invoice['Status'],
                    'invoice_subtotal' => $invoice['SubTotal'],
                    'invoice_vat' => $invoice['TotalTax'],
                    'invoice_total' => $invoice['Total'],
                    'additional' => true,
                    'sent' => false,
                    'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                ],
                'created_at' => now(),
            ]);

            if ($emailInvoice) {
                try {
                    $emailResult = xeroEmailAdditionalInvoiceToCustomer(
                        (int) $enquiry->id,
                        (string) $invoice['InvoiceID'],
                        (string) $invoice['InvoiceNumber']
                    );
                    $response['emailed'] = true;
                    $response['to_email'] = $emailResult['to_email'];

                    EnquiryEvent::query()->create([
                        'enquiry_id' => $enquiry->id,
                        'event_type' => 'xero_additional_invoice_emailed',
                        'message' => 'Additional attendees invoice emailed to the customer via Brevo (PDF attached).',
                        'metadata' => [
                            'channel' => 'brevo',
                            'source' => $source,
                            'to_email' => $emailResult['to_email'],
                            'xero_invoice_id' => $invoice['InvoiceID'],
                            'xero_invoice_number' => $emailResult['invoice_number'] !== ''
                                ? $emailResult['invoice_number']
                                : ($invoice['InvoiceNumber'] !== '' ? $invoice['InvoiceNumber'] : null),
                            'brevo_message_id' => $emailResult['brevo_message_id'],
                            'email_kind' => 'additional_invoice',
                            'additional' => true,
                            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                        ],
                        'created_at' => now(),
                    ]);
                } catch (Throwable $emailError) {
                    $response['email_error'] = $emailError->getMessage();
                    EnquiryEvent::query()->create([
                        'enquiry_id' => $enquiry->id,
                        'event_type' => 'xero_additional_invoice_email_failed',
                        'message' => 'Additional attendees invoice email could not be sent.',
                        'metadata' => [
                            'error' => $emailError->getMessage(),
                            'source' => $source,
                            'xero_invoice_id' => $invoice['InvoiceID'],
                            'xero_invoice_number' => $invoice['InvoiceNumber'] !== '' ? $invoice['InvoiceNumber'] : null,
                            'additional' => true,
                        ],
                        'created_at' => now(),
                    ]);
                }
            }
        } catch (Throwable $invoiceError) {
            $response['ok'] = false;
            $response['invoice_error'] = $invoiceError->getMessage();

            EnquiryEvent::query()->create([
                'enquiry_id' => $enquiry->id,
                'event_type' => 'xero_additional_invoice_failed',
                'message' => 'Could not create additional attendees invoice in Xero.',
                'metadata' => [
                    'channel' => 'xero',
                    'source' => $source,
                    'delta' => $count,
                    'unit_amount' => $unitAmount,
                    'error' => $invoiceError->getMessage(),
                    'additional' => true,
                ],
                'created_at' => now(),
            ]);

            $this->storeApiResponseSnapshot($addedEvent, $response);

            return response()->json($response, 502);
        }

        $this->storeApiResponseSnapshot($addedEvent, $response);

        $status = ($response['email_error'] ?? null) !== null ? 207 : 200;

        return response()->json($response, $status);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function storeApiResponseSnapshot(EnquiryEvent $addedEvent, array $response): void
    {
        $metadata = is_array($addedEvent->metadata) ? $addedEvent->metadata : [];
        $metadata['api_response'] = $response;
        $addedEvent->forceFill(['metadata' => $metadata])->save();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findIdempotentResult(int $enquiryId, string $idempotencyKey): ?array
    {
        $event = EnquiryEvent::query()
            ->where('enquiry_id', $enquiryId)
            ->where('event_type', 'attendees_added')
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('id')
            ->get()
            ->first(function (EnquiryEvent $event) use ($idempotencyKey): bool {
                $key = trim((string) data_get($event->metadata, 'idempotency_key', ''));

                return $key !== '' && hash_equals($key, $idempotencyKey);
            });

        if ($event === null) {
            return null;
        }

        $snapshot = data_get($event->metadata, 'api_response');

        return is_array($snapshot) ? $snapshot : null;
    }

    private function findRecentDuplicateAdd(int $enquiryId, int $count, string $source): ?EnquiryEvent
    {
        return EnquiryEvent::query()
            ->where('enquiry_id', $enquiryId)
            ->where('event_type', 'attendees_added')
            ->where('created_at', '>=', now()->subSeconds(self::DUPLICATE_WINDOW_SECONDS))
            ->orderByDesc('id')
            ->get()
            ->first(function (EnquiryEvent $event) use ($count, $source): bool {
                if ((string) data_get($event->metadata, 'source', '') === 'admin_correction') {
                    return false;
                }

                return (int) data_get($event->metadata, 'delta', 0) === $count
                    && trim((string) data_get($event->metadata, 'source', '')) === $source;
            });
    }

    private function tokenIsValid(Request $request): bool
    {
        $expected = trim((string) (getenv('ENQUIRY_API_TOKEN') ?: Setting::getValue('enquiry_api_token', '')));
        if ($expected === '') {
            return false;
        }

        $provided = trim((string) $request->bearerToken());
        if ($provided === '') {
            $provided = trim((string) $request->header('X-Api-Token', ''));
        }
        if ($provided === '') {
            $provided = trim((string) $request->query('token', ''));
        }

        return $provided !== '' && hash_equals($expected, $provided);
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
