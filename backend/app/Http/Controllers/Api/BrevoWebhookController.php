<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class BrevoWebhookController extends Controller
{
    /**
     * Brevo transactional webhook receiver (opened / uniqueOpened).
     *
     * Register in Brevo → Transactional → Settings → Webhook:
     *   {APP_URL}/api/brevo/webhooks?token={BREVO_WEBHOOK_SECRET}
     *
     * Enable Opened (and preferably Unique opened). Open tracking must be on
     * for transactional emails in the Brevo account.
     */
    public function __invoke(Request $request): Response
    {
        try {
            $root = dirname(base_path());
            require_once $root.'/config.php';
            require_once $root.'/brevo_email.php';

            if (! $this->tokenIsValid($request)) {
                return response('Unauthorized', 401);
            }

            $payload = $request->all();
            if ($payload === []) {
                $raw = $request->getContent();
                $decoded = json_decode($raw, true);
                $payload = is_array($decoded) ? $decoded : [];
            }

            // Brevo sometimes batches events as a list.
            $events = [];
            if (isset($payload[0]) && is_array($payload[0])) {
                $events = $payload;
            } elseif ($payload !== []) {
                $events = [$payload];
            }

            foreach ($events as $event) {
                if (! is_array($event)) {
                    continue;
                }
                brevoHandleTransactionalOpenWebhook($event);
            }

            return response('OK', 200);
        } catch (Throwable $e) {
            Log::error('Brevo webhook handler error', ['error' => $e->getMessage()]);

            // Acknowledge to avoid endless retries for malformed payloads we cannot process.
            return response('OK', 200);
        }
    }

    private function tokenIsValid(Request $request): bool
    {
        $expected = brevoWebhookSecret();
        if ($expected === '') {
            // No secret configured — allow (open tracking still works). Prefer setting one.
            return true;
        }

        $provided = trim((string) $request->query('token', ''));
        if ($provided === '') {
            $provided = trim((string) $request->header('X-Brevo-Webhook-Token', ''));
        }

        return $provided !== '' && hash_equals($expected, $provided);
    }
}
