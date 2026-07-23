<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class XeroWebhookController extends Controller
{
    /**
     * Xero webhook receiver.
     *
     * URL to register in the Xero developer portal:
     *   {APP_URL}/api/xero/webhooks
     *
     * Handles the signed “intent to receive” challenge and invoice create/update
     * events. Invoice payloads only identify the resource — we then load the
     * invoice from Xero and run the existing sent-invoice progression.
     */
    public function __invoke(Request $request): Response
    {
        $rawBody = $request->getContent();
        $signature = trim((string) $request->header('x-xero-signature', ''));
        $webhookKey = $this->webhookKey();

        if ($webhookKey === '') {
            Log::warning('Xero webhook received but XERO_WEBHOOK_KEY is not configured.');

            return response('', 401);
        }

        if (! $this->signatureIsValid($rawBody, $signature, $webhookKey)) {
            return response('', 401);
        }

        // Intent-to-receive / empty payload: signature OK → 200 empty body.
        $payload = json_decode($rawBody, true);
        if (! is_array($payload) || empty($payload['events']) || ! is_array($payload['events'])) {
            return response('', 200);
        }

        $root = dirname(base_path());
        require_once $root.'/config.php';
        require_once $root.'/xero.php';
        require_once $root.'/enquiry_logger.php';

        foreach ($payload['events'] as $event) {
            if (! is_array($event)) {
                continue;
            }

            $category = strtoupper(trim((string) ($event['eventCategory'] ?? '')));
            $type = strtoupper(trim((string) ($event['eventType'] ?? '')));
            $resourceId = trim((string) ($event['resourceId'] ?? ''));

            if ($category !== 'INVOICE' || $resourceId === '') {
                continue;
            }

            // CREATE can matter if a draft was authorised+sent quickly; UPDATE covers mark-as-sent.
            if (! in_array($type, ['UPDATE', 'CREATE'], true)) {
                continue;
            }

            try {
                $this->processInvoiceEvent($resourceId);
            } catch (Throwable $e) {
                Log::error('Xero webhook invoice processing failed', [
                    'resource_id' => $resourceId,
                    'event_type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response('', 200);
    }

    private function webhookKey(): string
    {
        $fromEnv = trim((string) (getenv('XERO_WEBHOOK_KEY') ?: ''));
        if ($fromEnv !== '') {
            return ltrim($fromEnv, '/');
        }

        return ltrim(trim((string) Setting::getValue('xero_webhook_key', '')), '/');
    }

    private function signatureIsValid(string $payload, string $signature, string $webhookKey): bool
    {
        if ($payload === '' || $signature === '' || $webhookKey === '') {
            return false;
        }

        $computed = base64_encode(hash_hmac('sha256', $payload, $webhookKey, true));

        return hash_equals($computed, $signature);
    }

    private function processInvoiceEvent(string $invoiceId): void
    {
        $enquiry = Enquiry::query()
            ->where('xero_invoice_id', $invoiceId)
            ->orderByDesc('id')
            ->first();

        if ($enquiry === null) {
            return;
        }

        // Already processed — ignore further invoice noise (edits, payments, etc.).
        if ($enquiry->xero_invoice_sent_at !== null) {
            return;
        }

        $result = xeroMaybeProcessInvoiceSent((int) $enquiry->id);
        if (is_array($result) && ! empty($result['processed'])) {
            Log::info('Xero webhook progressed invoice-sent enquiry', [
                'enquiry_id' => $enquiry->id,
                'xero_invoice_id' => $invoiceId,
            ]);
        }
    }
}
