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
     * Delivery URL (Xero developer portal → Webhooks):
     *   {APP_URL}/api/xero/webhooks
     *
     * Intent to receive: return 200 for a correctly signed payload and 401 for
     * an incorrectly signed one. Body must be empty. Never return other statuses.
     */
    public function __invoke(Request $request): Response
    {
        try {
            // Prefer php://input — Laravel getContent() is usually fine, but Xero
            // HMAC must match the exact bytes delivered on the wire.
            $rawBody = file_get_contents('php://input');
            if ($rawBody === false || $rawBody === '') {
                $rawBody = $request->getContent();
            }

            $signature = (string) $request->header('x-xero-signature', '');
            $webhookKey = $this->webhookKey();

            if ($webhookKey === '') {
                Log::warning('Xero webhook: signing key is not configured.');

                return $this->emptyStatus(401);
            }

            if ($signature === '' || ! $this->signatureIsValid($rawBody, $signature, $webhookKey)) {
                return $this->emptyStatus(401);
            }

            $payload = json_decode($rawBody, true);
            $events = is_array($payload) && isset($payload['events']) && is_array($payload['events'])
                ? $payload['events']
                : [];

            // Intent-to-receive uses an empty events array — acknowledge immediately.
            if ($events === []) {
                return $this->emptyStatus(200);
            }

            $this->processEvents($events);

            return $this->emptyStatus(200);
        } catch (Throwable $e) {
            // Xero ITR fails on any non-2xx/401. Never leak a 500 to the challenge.
            Log::error('Xero webhook handler error', ['error' => $e->getMessage()]);

            return $this->emptyStatus(401);
        }
    }

    private function emptyStatus(int $status): Response
    {
        return response('', $status, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function webhookKey(): string
    {
        // Never call env() here — it is null when config is cached in production.
        $candidates = [
            (string) config('services.xero.webhook_key', ''),
            (string) ($_ENV['XERO_WEBHOOK_KEY'] ?? ''),
            (string) ($_SERVER['XERO_WEBHOOK_KEY'] ?? ''),
            (string) (getenv('XERO_WEBHOOK_KEY') ?: ''),
            (string) Setting::getValue('xero_webhook_key', ''),
        ];

        foreach ($candidates as $key) {
            $key = $this->normaliseStoredKey($key);
            if ($key !== '') {
                return $key;
            }
        }

        return '';
    }

    /**
     * Xero's portal often shows the signing key with a leading "/". That slash
     * is part of the HMAC secret when present — do not strip it for storage.
     */
    private function normaliseStoredKey(string $key): string
    {
        $key = trim($key);
        // Coolify / .env sometimes wraps values in quotes.
        if (
            (str_starts_with($key, '"') && str_ends_with($key, '"'))
            || (str_starts_with($key, "'") && str_ends_with($key, "'"))
        ) {
            $key = substr($key, 1, -1);
            $key = trim($key);
        }

        return $key;
    }

    /**
     * @return list<string>
     */
    private function webhookKeyVariants(string $webhookKey): array
    {
        $webhookKey = $this->normaliseStoredKey($webhookKey);
        if ($webhookKey === '') {
            return [];
        }

        $variants = [$webhookKey];
        $withoutSlash = ltrim($webhookKey, '/');
        if ($withoutSlash !== '' && $withoutSlash !== $webhookKey) {
            $variants[] = $withoutSlash;
        }
        if (! str_starts_with($webhookKey, '/')) {
            $variants[] = '/'.$webhookKey;
        }

        return array_values(array_unique($variants));
    }

    private function signatureIsValid(string $payload, string $signature, string $webhookKey): bool
    {
        if ($payload === '' || $signature === '' || $webhookKey === '') {
            return false;
        }

        foreach ($this->webhookKeyVariants($webhookKey) as $candidate) {
            $computed = base64_encode(hash_hmac('sha256', $payload, $candidate, true));
            if (hash_equals($computed, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<mixed>  $events
     */
    private function processEvents(array $events): void
    {
        $root = dirname(base_path());
        require_once $root.'/config.php';
        require_once $root.'/xero.php';
        require_once $root.'/enquiry_logger.php';

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $category = strtoupper(trim((string) ($event['eventCategory'] ?? '')));
            $type = strtoupper(trim((string) ($event['eventType'] ?? '')));
            $resourceId = trim((string) ($event['resourceId'] ?? ''));

            if ($category !== 'INVOICE' || $resourceId === '') {
                continue;
            }

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
    }

    private function processInvoiceEvent(string $invoiceId): void
    {
        $enquiry = Enquiry::query()
            ->where('xero_invoice_id', $invoiceId)
            ->orderByDesc('id')
            ->first();

        if ($enquiry === null || $enquiry->xero_invoice_sent_at !== null) {
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
