<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'monday_api_token' => '',
            'monday_board_id' => '',
            'monday_group_id' => '',
            'monday_group_name' => 'New Enquiries',
            'monday_booking_group_name' => 'Quote Accepted',
            'ideal_postcodes_api_key' => '',
            'kajabi_courses_url' => 'https://safer-handling.mykajabi.com/store',
            'brevo_api_key' => '',
            'brevo_email_enabled' => '0',
            'brevo_sender_email' => 'training@safer-handling.co.uk',
            'brevo_sender_name' => 'Safer Handling',
            'brevo_contact_email' => 'training@safer-handling.co.uk',
            'brevo_office_email' => 'office@safer-handling.co.uk',
            'brevo_lead_notification_enabled' => '1',
            'brevo_logo_url' => 'https://img.mailinblue.com/8246699/images/content_library/original/6a02cfcf9d7025c9e500ab4b.jpg',
            'brevo_quote_accept_url' => '',
            'form_base_url' => '',
            'brevo_resume_email_enabled' => '1',
            'office_auto_reply_enabled' => '0',
            'office_auto_reply_hours' => '8',
            'office_imap_host' => 'outlook.office365.com',
            'office_imap_port' => '993',
            'office_imap_encryption' => 'ssl',
            'office_imap_username' => 'office@safer-handling.co.uk',
            'office_imap_password' => '',
            'office_imap_inbox_folder' => 'INBOX',
            'office_imap_sent_folder' => 'Sent Items',
            'xero_enabled' => '0',
            'xero_client_id' => '',
            'xero_client_secret' => '',
            'xero_redirect_uri' => '',
            'xero_tenant_id' => '',
            'xero_access_token' => '',
            'xero_refresh_token' => '',
            'xero_token_expires_at' => '',
            'xero_default_item_code' => '',
            'xero_sales_account_code' => '200',
            'xero_vat_rate' => '20',
            'xero_branding_theme_id' => '',
            'xero_webhook_key' => '',
            'forge_enabled' => '0',
            'forge_webhook_url' => 'https://saferhandling.forgecrm.co.uk/safer_production/webhooks/bookings/',
            'forge_webhook_token' => '',
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        Setting::flushCache();
    }
}
