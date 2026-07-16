<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class ImportEnvSettingsCommand extends Command
{
    protected $signature = 'settings:import-env
                            {--force : Overwrite existing non-empty database values}
                            {--dry-run : Show what would be imported without writing}';

    protected $description = 'Import Monday/Brevo/postcode integration variables from the environment into the settings database';

    /**
     * Env var => settings table key.
     *
     * @var array<string, string>
     */
    private array $map = [
        'MONDAY_API_TOKEN' => 'monday_api_token',
        'MONDAY_BOARD_ID' => 'monday_board_id',
        'MONDAY_GROUP_ID' => 'monday_group_id',
        'MONDAY_GROUP_NAME' => 'monday_group_name',
        'MONDAY_BOOKING_GROUP_NAME' => 'monday_booking_group_name',
        'IDEAL_POSTCODES_API_KEY' => 'ideal_postcodes_api_key',
        'BREVO_API_KEY' => 'brevo_api_key',
        'BREVO_EMAIL_ENABLED' => 'brevo_email_enabled',
        'BREVO_RESUME_EMAIL_ENABLED' => 'brevo_resume_email_enabled',
        'BREVO_SENDER_EMAIL' => 'brevo_sender_email',
        'BREVO_SENDER_NAME' => 'brevo_sender_name',
        'BREVO_CONTACT_EMAIL' => 'brevo_contact_email',
        'BREVO_OFFICE_EMAIL' => 'brevo_office_email',
        'BREVO_LEAD_NOTIFICATION_ENABLED' => 'brevo_lead_notification_enabled',
        'BREVO_LOGO_URL' => 'brevo_logo_url',
        'BREVO_QUOTE_ACCEPT_URL' => 'brevo_quote_accept_url',
        'FORM_BASE_URL' => 'form_base_url',
        'XERO_ENABLED' => 'xero_enabled',
        'XERO_CLIENT_ID' => 'xero_client_id',
        'XERO_CLIENT_SECRET' => 'xero_client_secret',
        'XERO_REDIRECT_URI' => 'xero_redirect_uri',
        'XERO_TENANT_ID' => 'xero_tenant_id',
        'XERO_DEFAULT_ITEM_CODE' => 'xero_default_item_code',
        'XERO_SALES_ACCOUNT_CODE' => 'xero_sales_account_code',
        'XERO_VAT_RATE' => 'xero_vat_rate',
        'XERO_BRANDING_THEME_ID' => 'xero_branding_theme_id',
    ];

    /**
     * @var list<string>
     */
    private array $booleanKeys = [
        'brevo_email_enabled',
        'brevo_resume_email_enabled',
        'brevo_lead_notification_enabled',
        'xero_enabled',
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $imported = 0;
        $skipped = 0;

        foreach ($this->map as $envKey => $settingKey) {
            $raw = getenv($envKey);
            if ($raw === false || trim((string) $raw) === '') {
                $this->line("skip  {$settingKey}  ({$envKey} not set)");
                $skipped++;

                continue;
            }

            $value = trim((string) $raw);
            if (in_array($settingKey, $this->booleanKeys, true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            }

            $existing = (string) (Setting::getValue($settingKey, '') ?? '');
            if ($existing !== '' && ! $force) {
                $this->line("keep  {$settingKey}  (already set; use --force to overwrite)");
                $skipped++;

                continue;
            }

            $display = $this->redact($settingKey, $value);
            if ($dryRun) {
                $this->info("would import  {$settingKey} = {$display}");
            } else {
                Setting::setValue($settingKey, $value);
                $this->info("import  {$settingKey} = {$display}");
            }
            $imported++;
        }

        Setting::flushCache();

        $this->newLine();
        $this->comment($dryRun
            ? "Dry run complete: {$imported} would import, {$skipped} skipped."
            : "Done: {$imported} imported, {$skipped} skipped.");
        $this->comment('Manage these values in Admin → Settings. Env vars remain optional overrides.');

        return self::SUCCESS;
    }

    private function redact(string $key, string $value): string
    {
        if (str_contains($key, 'token') || str_contains($key, 'api_key') || str_contains($key, 'key')) {
            $len = strlen($value);

            return $len <= 8 ? str_repeat('*', $len) : substr($value, 0, 4).str_repeat('*', max(0, $len - 8)).substr($value, -4);
        }

        return $value;
    }
}
