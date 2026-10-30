<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\EnquiryEvent;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class EnquiryAttendeesController extends Controller
{
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
        ]);

        $count = (int) $validated['count'];
        $source = trim((string) ($validated['source'] ?? ''));
        if ($source === '') {
            $source = 'API';
        }
        $emailInvoice = array_key_exists('email_invoice', $validated)
            ? (bool) $validated['email_invoice']
            : true;

        $field = $this->resolveAttendeeField($enquiry);
        $previous = max(0, (int) ($enquiry->{$field} ?? 0));
        $newTotal = $previous + $count;

        $enquiry->{$field} = $newTotal;
        $enquiry->save();

        $sourceLabel = $source;
        $message = $count === 1
            ? "1 new attendee from {$sourceLabel}"
            : "{$count} new attendees from {$sourceLabel}";

        EnquiryEvent::query()->create([
            'enquiry_id' => $enquiry->id,
            'event_type' => 'attendees_added',
            'message' => $message,
            'metadata' => [
                'source' => $source,
                'delta' => $count,
                'previous_attendees' => $previous,
                'attendees' => $newTotal,
                'attendee_field' => $field,
            ],
            'created_at' => now(),
        ]);

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
            'attendee_field' => $field,
            'unit_amount' => $unitAmount,
            'net_total' => round($unitAmount * $count, 2),
            'invoice' => null,
            'emailed' => false,
            'to_email' => null,
            'invoice_error' => null,
            'email_error' => null,
        ];

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

            return response()->json($response, 502);
        }

        $status = ($response['email_error'] ?? null) !== null ? 207 : 200;

        return response()->json($response, $status);
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

    /**
     * Prefer the attendee column that already has a count; otherwise default to attendees.
     */
    private function resolveAttendeeField(Enquiry $enquiry): string
    {
        $attendees = (int) ($enquiry->attendees ?? 0);
        $matrix = (int) ($enquiry->matrix_attendees ?? 0);
        $trainer = (int) ($enquiry->trainer_attendees ?? 0);

        if ($attendees > 0) {
            return 'attendees';
        }
        if ($matrix > 0) {
            return 'matrix_attendees';
        }
        if ($trainer > 0) {
            return 'trainer_attendees';
        }

        return 'attendees';
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
