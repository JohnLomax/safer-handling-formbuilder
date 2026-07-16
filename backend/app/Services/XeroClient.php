<?php

namespace App\Services;

use App\Models\Setting;
use RuntimeException;

class XeroClient
{
    private const AUTH_URL = 'https://login.xero.com/identity/connect/authorize';

    private const TOKEN_URL = 'https://identity.xero.com/connect/token';

    private const CONNECTIONS_URL = 'https://api.xero.com/connections';

    private const API_BASE = 'https://api.xero.com/api.xro/2.0';

    // New Xero apps (from Mar 2026) reject deprecated accounting.transactions.
    // Quotes + Items are covered by accounting.invoices.
    private const SCOPES = 'openid profile email offline_access accounting.contacts accounting.invoices accounting.settings.read';

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '' && $this->redirectUri() !== '';
    }

    public function isConnected(): bool
    {
        return $this->isConfigured()
            && $this->tenantId() !== ''
            && (trim((string) Setting::getValue('xero_refresh_token', '')) !== ''
                || trim((string) Setting::getValue('xero_access_token', '')) !== '');
    }

    public function authorizationUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Xero client ID, client secret, and redirect URI must be configured.');
        }

        return self::AUTH_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => self::SCOPES,
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): void
    {
        $tokens = $this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ]);

        $this->storeTokens($tokens);
        $this->discoverTenant();
    }

    public function disconnect(): void
    {
        foreach ([
            'xero_access_token',
            'xero_refresh_token',
            'xero_token_expires_at',
            'xero_tenant_id',
            'xero_tenant_name',
        ] as $key) {
            Setting::setValue($key, '');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $url = self::API_BASE.'/'.ltrim($path, '/');
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $this->apiRequest('GET', $url);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload): array
    {
        return $this->apiRequest('POST', self::API_BASE.'/'.ltrim($path, '/'), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function put(string $path, array $payload): array
    {
        return $this->apiRequest('PUT', self::API_BASE.'/'.ltrim($path, '/'), $payload);
    }

    public function getPdf(string $path): string
    {
        $url = self::API_BASE.'/'.ltrim($path, '/');
        $response = $this->rawRequest('GET', $url, null, [
            'Accept: application/pdf',
            'Authorization: Bearer '.$this->accessToken(),
            'Xero-tenant-id: '.$this->tenantId(),
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('Could not download Xero PDF (HTTP '.$response['status'].').');
        }

        return $response['body'];
    }

    public function clientId(): string
    {
        return trim((string) (getenv('XERO_CLIENT_ID') ?: Setting::getValue('xero_client_id', '')));
    }

    public function clientSecret(): string
    {
        return trim((string) (getenv('XERO_CLIENT_SECRET') ?: Setting::getValue('xero_client_secret', '')));
    }

    public function redirectUri(): string
    {
        $configured = trim((string) (getenv('XERO_REDIRECT_URI') ?: Setting::getValue('xero_redirect_uri', '')));
        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url'), '/').'/admin/settings/xero/callback';
    }

    public function tenantId(): string
    {
        return trim((string) (getenv('XERO_TENANT_ID') ?: Setting::getValue('xero_tenant_id', '')));
    }

    public function defaultTaxType(): string
    {
        $tax = trim((string) (getenv('XERO_DEFAULT_TAX_TYPE') ?: Setting::getValue('xero_default_tax_type', 'OUTPUT2')));

        return $tax !== '' ? $tax : 'OUTPUT2';
    }

    private function accessToken(): string
    {
        $this->refreshTokenIfNeeded();

        $token = trim((string) Setting::getValue('xero_access_token', ''));
        if ($token === '') {
            throw new RuntimeException('Xero is not connected. Connect it in Admin → Settings.');
        }

        return $token;
    }

    private function refreshTokenIfNeeded(): void
    {
        $expiresAt = (int) Setting::getValue('xero_token_expires_at', '0');
        if ($expiresAt > 0 && time() < ($expiresAt - 60)) {
            return;
        }

        $refreshToken = trim((string) Setting::getValue('xero_refresh_token', ''));
        if ($refreshToken === '') {
            throw new RuntimeException('Xero refresh token is missing. Reconnect Xero in Admin → Settings.');
        }

        $tokens = $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        $this->storeTokens($tokens);
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, mixed>
     */
    private function requestToken(array $fields): array
    {
        $response = $this->rawRequest('POST', self::TOKEN_URL, http_build_query($fields), [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic '.base64_encode($this->clientId().':'.$this->clientSecret()),
        ]);

        $decoded = json_decode($response['body'], true);
        if ($response['status'] < 200 || $response['status'] >= 300 || ! is_array($decoded)) {
            $message = is_array($decoded) ? (string) ($decoded['error_description'] ?? $decoded['error'] ?? '') : '';
            throw new RuntimeException($message !== '' ? $message : 'Xero token request failed (HTTP '.$response['status'].').');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    private function storeTokens(array $tokens): void
    {
        Setting::setValue('xero_access_token', (string) ($tokens['access_token'] ?? ''));
        if (! empty($tokens['refresh_token'])) {
            Setting::setValue('xero_refresh_token', (string) $tokens['refresh_token']);
        }
        $expiresIn = (int) ($tokens['expires_in'] ?? 1800);
        Setting::setValue('xero_token_expires_at', (string) (time() + max(60, $expiresIn)));
    }

    private function discoverTenant(): void
    {
        $response = $this->rawRequest('GET', self::CONNECTIONS_URL, null, [
            'Accept: application/json',
            'Authorization: Bearer '.trim((string) Setting::getValue('xero_access_token', '')),
        ]);

        $decoded = json_decode($response['body'], true);
        if ($response['status'] < 200 || $response['status'] >= 300 || ! is_array($decoded) || $decoded === []) {
            throw new RuntimeException('Could not load Xero organisations. Reconnect and grant access.');
        }

        $preferred = trim((string) Setting::getValue('xero_tenant_id', ''));
        $chosen = $decoded[0];
        foreach ($decoded as $connection) {
            if ($preferred !== '' && ($connection['tenantId'] ?? '') === $preferred) {
                $chosen = $connection;
                break;
            }
        }

        Setting::setValue('xero_tenant_id', (string) ($chosen['tenantId'] ?? ''));
        Setting::setValue('xero_tenant_name', (string) ($chosen['tenantName'] ?? ''));
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    private function apiRequest(string $method, string $url, ?array $json = null): array
    {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->accessToken(),
            'Xero-tenant-id: '.$this->tenantId(),
        ];

        $body = $json !== null ? json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $response = $this->rawRequest($method, $url, $body, $headers);
        $decoded = json_decode($response['body'], true);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $message = '';
            if (is_array($decoded)) {
                $message = (string) ($decoded['Message'] ?? '');
                if ($message === '' && ! empty($decoded['Elements'][0]['ValidationErrors'][0]['Message'])) {
                    $message = (string) $decoded['Elements'][0]['ValidationErrors'][0]['Message'];
                }
            }
            throw new RuntimeException($message !== '' ? $message : 'Xero API request failed (HTTP '.$response['status'].').');
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  list<string>  $headers
     * @return array{status:int,body:string}
     */
    private function rawRequest(string $method, string $url, ?string $body, array $headers): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize cURL for Xero.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Xero request failed: '.$err);
        }
        curl_close($ch);

        return ['status' => $status, 'body' => (string) $raw];
    }
}
