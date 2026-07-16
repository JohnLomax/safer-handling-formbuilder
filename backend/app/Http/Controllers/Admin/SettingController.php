<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

class SettingController extends Controller
{
    /**
     * @var list<string>
     */
    private array $keys = [
        'monday_api_token',
        'monday_board_id',
        'monday_group_id',
        'monday_group_name',
        'monday_booking_group_name',
        'ideal_postcodes_api_key',
        'brevo_api_key',
        'brevo_email_enabled',
        'brevo_sender_email',
        'brevo_sender_name',
        'brevo_contact_email',
        'brevo_office_email',
        'brevo_lead_notification_enabled',
        'brevo_logo_url',
        'brevo_quote_accept_url',
        'form_base_url',
        'brevo_resume_email_enabled',
        'xero_enabled',
        'xero_client_id',
        'xero_client_secret',
        'xero_redirect_uri',
        'xero_tenant_id',
        'xero_default_item_code',
        'xero_sales_account_code',
        'xero_vat_rate',
        'xero_branding_theme_id',
    ];

    public function edit(): View
    {
        $settings = Setting::allCached();
        $redirectUri = $settings['xero_redirect_uri'] ?? '';
        if ($redirectUri === '') {
            $redirectUri = url('/admin/settings/xero/callback');
        }

        return view('admin.settings.edit', [
            'settings' => $settings,
            'xeroRedirectUri' => $redirectUri,
            'xeroConnected' => trim((string) ($settings['xero_refresh_token'] ?? '')) !== ''
                && trim((string) ($settings['xero_tenant_id'] ?? '')) !== '',
            'xeroTokenExpiresAt' => (int) ($settings['xero_token_expires_at'] ?? 0),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'monday_api_token' => ['nullable', 'string'],
            'monday_board_id' => ['nullable', 'string', 'max:50'],
            'monday_group_id' => ['nullable', 'string', 'max:50'],
            'monday_group_name' => ['nullable', 'string', 'max:255'],
            'monday_booking_group_name' => ['nullable', 'string', 'max:255'],
            'ideal_postcodes_api_key' => ['nullable', 'string'],
            'brevo_api_key' => ['nullable', 'string'],
            'brevo_email_enabled' => ['nullable', 'boolean'],
            'brevo_sender_email' => ['nullable', 'email', 'max:255'],
            'brevo_sender_name' => ['nullable', 'string', 'max:255'],
            'brevo_contact_email' => ['nullable', 'email', 'max:255'],
            'brevo_office_email' => ['nullable', 'email', 'max:255'],
            'brevo_lead_notification_enabled' => ['nullable', 'boolean'],
            'brevo_logo_url' => ['nullable', 'url', 'max:500'],
            'brevo_quote_accept_url' => ['nullable', 'string', 'max:500'],
            'form_base_url' => ['nullable', 'url', 'max:500'],
            'brevo_resume_email_enabled' => ['nullable', 'boolean'],
            'xero_enabled' => ['nullable', 'boolean'],
            'xero_client_id' => ['nullable', 'string', 'max:255'],
            'xero_client_secret' => ['nullable', 'string'],
            'xero_redirect_uri' => ['nullable', 'url', 'max:500'],
            'xero_tenant_id' => ['nullable', 'string', 'max:255'],
            'xero_default_item_code' => ['nullable', 'string', 'max:100'],
            'xero_sales_account_code' => ['nullable', 'string', 'max:50'],
            'xero_vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'xero_branding_theme_id' => ['nullable', 'string', 'max:100'],
        ]);

        foreach ($this->keys as $key) {
            if (in_array($key, [
                'brevo_email_enabled',
                'brevo_resume_email_enabled',
                'brevo_lead_notification_enabled',
                'xero_enabled',
            ], true)) {
                Setting::setValue($key, $request->boolean($key));

                continue;
            }

            $incoming = $validated[$key] ?? '';

            if ($incoming === '' && in_array($key, [
                'monday_api_token',
                'ideal_postcodes_api_key',
                'brevo_api_key',
                'xero_client_secret',
            ], true)) {
                continue;
            }

            Setting::setValue($key, $incoming);
        }

        return redirect()->route('admin.settings.edit')->with('status', 'Configuration saved.');
    }

    public function connectXero(): RedirectResponse
    {
        $clientId = trim((string) Setting::getValue('xero_client_id', ''));

        if ($clientId === '') {
            return redirect()
                ->route('admin.settings.edit')
                ->withErrors(['xero' => 'Add your Xero client ID before connecting.']);
        }

        // Always use the current request host so the cookie/session and Xero
        // redirect URI stay on the same origin (localhost vs 127.0.0.1).
        $redirectUri = url('/admin/settings/xero/callback');
        Setting::setValue('xero_redirect_uri', $redirectUri);

        $state = bin2hex(random_bytes(16));
        session(['xero_oauth_state' => $state]);
        // Persist before leaving the app — otherwise the external Xero redirect
        // can race the session write and the callback sees an empty state.
        session()->save();
        Setting::setValue('xero_oauth_state', $state);
        Setting::setValue('xero_oauth_state_expires_at', (string) (time() + 600));

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            // New Xero apps (from Mar 2026) reject deprecated accounting.transactions.
            // Quotes + Items are covered by accounting.invoices.
            'scope' => 'offline_access accounting.contacts accounting.invoices accounting.settings.read',
            'state' => $state,
        ]);

        return redirect()->away('https://login.xero.com/identity/connect/authorize?'.$query);
    }

    public function xeroCallback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');
        $code = trim((string) $request->query('code', ''));

        if (! $this->consumeXeroOAuthState($state)) {
            return redirect()
                ->route('admin.settings.edit')
                ->withErrors([
                    'xero' => 'Xero connection failed because the OAuth state was invalid. Use the same host you started from (e.g. http://localhost:8000, not 127.0.0.1), then try Connect again.',
                ]);
        }

        if ($code === '') {
            $error = trim((string) $request->query('error_description', $request->query('error', 'Xero connection was cancelled.')));

            return redirect()
                ->route('admin.settings.edit')
                ->withErrors(['xero' => $error]);
        }

        $clientId = trim((string) Setting::getValue('xero_client_id', ''));
        $clientSecret = trim((string) Setting::getValue('xero_client_secret', ''));
        $redirectUri = trim((string) Setting::getValue('xero_redirect_uri', ''));
        if ($redirectUri === '') {
            $redirectUri = url('/admin/settings/xero/callback');
        }

        try {
            $tokenResponse = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post('https://identity.xero.com/connect/token', [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ]);

            if (! $tokenResponse->successful()) {
                throw new \RuntimeException('Could not exchange Xero authorization code for tokens.');
            }

            $token = $tokenResponse->json();
            $accessToken = (string) ($token['access_token'] ?? '');
            $refreshToken = (string) ($token['refresh_token'] ?? '');
            $expiresIn = (int) ($token['expires_in'] ?? 1800);
            if ($accessToken === '' || $refreshToken === '') {
                throw new \RuntimeException('Xero did not return access and refresh tokens.');
            }

            $connectionsResponse = Http::withToken($accessToken)
                ->get('https://api.xero.com/connections');

            if (! $connectionsResponse->successful()) {
                throw new \RuntimeException('Could not load Xero organisation connections.');
            }

            $connections = $connectionsResponse->json();
            $tenantId = '';
            if (is_array($connections) && isset($connections[0]['tenantId'])) {
                $tenantId = (string) $connections[0]['tenantId'];
            }
            if ($tenantId === '') {
                throw new \RuntimeException('No Xero organisation was returned for this connection.');
            }

            Setting::setValue('xero_access_token', $accessToken);
            Setting::setValue('xero_refresh_token', $refreshToken);
            Setting::setValue('xero_token_expires_at', (string) (time() + max(60, $expiresIn)));
            Setting::setValue('xero_tenant_id', $tenantId);
            Setting::setValue('xero_enabled', true);

            return redirect()
                ->route('admin.settings.edit')
                ->with('status', 'Xero connected successfully.');
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.settings.edit')
                ->withErrors(['xero' => 'Xero connection failed: '.$e->getMessage()]);
        }
    }

    public function disconnectXero(): RedirectResponse
    {
        Setting::setValue('xero_access_token', '');
        Setting::setValue('xero_refresh_token', '');
        Setting::setValue('xero_token_expires_at', '');
        Setting::setValue('xero_tenant_id', '');
        Setting::setValue('xero_enabled', false);
        Setting::setValue('xero_oauth_state', '');
        Setting::setValue('xero_oauth_state_expires_at', '');

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'Xero disconnected.');
    }

    private function consumeXeroOAuthState(string $state): bool
    {
        if ($state === '') {
            return false;
        }

        $sessionState = (string) session('xero_oauth_state', '');
        $storedState = trim((string) Setting::getValue('xero_oauth_state', ''));
        $expiresAt = (int) Setting::getValue('xero_oauth_state_expires_at', '0');

        $sessionOk = $sessionState !== '' && hash_equals($sessionState, $state);
        $storedOk = $storedState !== ''
            && ($expiresAt === 0 || $expiresAt >= time())
            && hash_equals($storedState, $state);

        session()->forget('xero_oauth_state');
        Setting::setValue('xero_oauth_state', '');
        Setting::setValue('xero_oauth_state_expires_at', '');

        return $sessionOk || $storedOk;
    }
}
