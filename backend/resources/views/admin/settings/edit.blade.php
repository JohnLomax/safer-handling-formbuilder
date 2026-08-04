<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header title="Integration settings" description="These values are stored in the shared settings database and used by the public enquiry form. Optional server env vars (MONDAY_*, BREVO_*, etc.) override the database when set." />
    </x-slot>

    <div class="admin-shell">
        @include('admin.partials.alerts')

        {{-- Outside the settings form so nested actions do not nest <form> tags --}}
        <form id="xero-disconnect-form" method="POST" action="{{ route('admin.settings.xero.disconnect') }}" class="hidden">
            @csrf
        </form>
        <form id="brevo-register-webhook-form" method="POST" action="{{ route('admin.settings.brevo.register-webhook') }}" class="hidden">
            @csrf
        </form>

        <form method="POST" action="{{ route('admin.settings.update') }}" class="flex flex-col gap-6">
            @csrf
            @method('PUT')

            <div class="brand-panel space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-brand-header">Monday.com</h3>
                    <p class="mt-1 text-sm text-sh-mid">Used when creating and updating Monday board items from the enquiry form.</p>
                </div>

                <div>
                    <x-input-label for="monday_api_token" value="API token" />
                    <x-text-input id="monday_api_token" name="monday_api_token" type="password" class="mt-1 block w-full font-mono text-sm" :value="old('monday_api_token', $settings['monday_api_token'] ?? '')" autocomplete="off" />
                    <p class="mt-1 text-xs text-sh-mid">Leave blank when saving to keep the existing token.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="monday_board_id" value="Board ID" />
                        <x-text-input id="monday_board_id" name="monday_board_id" type="text" class="mt-1 block w-full" :value="old('monday_board_id', $settings['monday_board_id'] ?? '')" />
                    </div>
                    <div>
                        <x-input-label for="monday_group_id" value="Group ID (optional)" />
                        <x-text-input id="monday_group_id" name="monday_group_id" type="text" class="mt-1 block w-full" :value="old('monday_group_id', $settings['monday_group_id'] ?? '')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="monday_group_name" value="New enquiry group name" />
                    <x-text-input id="monday_group_name" name="monday_group_name" type="text" class="mt-1 block w-full" :value="old('monday_group_name', $settings['monday_group_name'] ?? 'New Enquiries')" />
                </div>

                <div>
                    <x-input-label for="monday_booking_group_name" value="Quote accepted group name" />
                    <x-text-input id="monday_booking_group_name" name="monday_booking_group_name" type="text" class="mt-1 block w-full" :value="old('monday_booking_group_name', $settings['monday_booking_group_name'] ?? 'Quote Accepted')" />
                    <p class="mt-1 text-xs text-sh-mid">After booking details are completed, the Monday item is moved here. If the group does not exist, it will be created (or “Won - Ready for Booking” is used if present).</p>
                </div>
            </div>

            <div class="brand-panel space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-brand-header">Postcode lookup</h3>
                    <p class="mt-1 text-sm text-sh-mid">Ideal Postcodes API key for UK address lookup on the form.</p>
                </div>

                <div>
                    <x-input-label for="ideal_postcodes_api_key" value="Ideal Postcodes API key" />
                    <x-text-input id="ideal_postcodes_api_key" name="ideal_postcodes_api_key" type="password" class="mt-1 block w-full font-mono text-sm" :value="old('ideal_postcodes_api_key', $settings['ideal_postcodes_api_key'] ?? '')" autocomplete="off" />
                    <p class="mt-1 text-xs text-sh-mid">Leave blank when saving to keep the existing key.</p>
                </div>
            </div>

            <div class="brand-panel space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-brand-header">Kajabi</h3>
                    <p class="mt-1 text-sm text-sh-mid">Online courses store link used when a customer chooses “I want to complete an online course” on the enquiry form.</p>
                </div>

                <div>
                    <x-input-label for="kajabi_courses_url" value="Online courses URL" />
                    <x-text-input id="kajabi_courses_url" name="kajabi_courses_url" type="url" class="mt-1 block w-full font-mono text-sm" :value="old('kajabi_courses_url', $settings['kajabi_courses_url'] ?? 'https://safer-handling.mykajabi.com/store')" placeholder="https://safer-handling.mykajabi.com/store" />
                    <p class="mt-1 text-xs text-sh-mid">Customers are redirected here after Monday is updated for an online-course enquiry.</p>
                </div>
            </div>

            <div class="brand-panel space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-brand-header">Brevo email</h3>
                    <p class="mt-1 text-sm text-sh-mid">Quote confirmation emails sent after form submission.</p>
                </div>

                <div>
                    <x-input-label for="brevo_api_key" value="Brevo API key" />
                    <x-text-input id="brevo_api_key" name="brevo_api_key" type="password" class="mt-1 block w-full font-mono text-sm" :value="old('brevo_api_key', $settings['brevo_api_key'] ?? '')" autocomplete="off" />
                    <p class="mt-1 text-xs text-sh-mid">Leave blank when saving to keep the existing key.</p>
                </div>

                <div class="flex items-center gap-2">
                    <input id="brevo_email_enabled" name="brevo_email_enabled" type="checkbox" value="1" class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand" @checked(old('brevo_email_enabled', filter_var($settings['brevo_email_enabled'] ?? '0', FILTER_VALIDATE_BOOLEAN)))>
                    <label for="brevo_email_enabled" class="text-sm text-sh-mid">Send Brevo quote confirmation emails (used when Xero quotes are disabled)</label>
                </div>

                <div class="flex items-center gap-2">
                    <input id="brevo_resume_email_enabled" name="brevo_resume_email_enabled" type="checkbox" value="1" class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand" @checked(old('brevo_resume_email_enabled', filter_var($settings['brevo_resume_email_enabled'] ?? '1', FILTER_VALIDATE_BOOLEAN)))>
                    <label for="brevo_resume_email_enabled" class="text-sm text-sh-mid">Send Edit Enquiry Emails after the full form is submitted</label>
                </div>

                <div class="rounded-[12px] border border-[#9fc8ed] bg-[#eef7ff] p-4 space-y-3">
                    <div>
                        <h4 class="text-sm font-semibold text-brand-header">Email open tracking webhook</h4>
                        <p class="mt-1 text-xs text-sh-mid">
                            Easiest: use <strong>Register in Brevo</strong> below (uses your Brevo API key).
                            Or add it manually in Brevo:
                        </p>
                        <ol class="mt-2 list-decimal space-y-1 ps-5 text-xs text-sh-mid">
                            <li>Click your account name (top right) → <strong>Integrations</strong> → <strong>Webhooks</strong></li>
                            <li>Click <strong>Add webhook</strong> → choose <strong>Outbound webhook</strong></li>
                            <li>Paste the URL below</li>
                            <li>Event category: <strong>Transactional email</strong> — keep <strong>Opened</strong> / <strong>Unique opened</strong> on</li>
                            <li>Click <strong>Activate webhook</strong></li>
                        </ol>
                        <p class="mt-2 text-xs text-sh-mid">
                            Direct link (if available on your account):
                            <a href="https://app.brevo.com/integrations/webhooks" class="link-brand" target="_blank" rel="noopener noreferrer">app.brevo.com/integrations/webhooks</a>
                            or older SMTP page
                            <a href="https://app-smtp.brevo.com/webhook" class="link-brand" target="_blank" rel="noopener noreferrer">app-smtp.brevo.com/webhook</a>.
                        </p>
                    </div>

                    @error('brevo_webhook')
                        <p class="text-xs font-medium text-red-700">{{ $message }}</p>
                    @enderror

                    <div>
                        <x-input-label for="brevo_webhook_url_display" value="Webhook URL" />
                        <div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-stretch">
                            <input
                                id="brevo_webhook_url_display"
                                type="text"
                                readonly
                                class="block w-full rounded-[10px] border border-[#b7d3ee] bg-white px-3 py-2 font-mono text-xs text-sh-text"
                                value="{{ $brevoWebhookUrl }}"
                            />
                            <button
                                type="button"
                                class="btn-brand-outline shrink-0 text-xs"
                                onclick="navigator.clipboard.writeText(document.getElementById('brevo_webhook_url_display').value)"
                            >
                                Copy URL
                            </button>
                        </div>
                        @if (! $brevoWebhookSecretConfigured)
                            <p class="mt-1 text-xs text-amber-800">
                                Tip: enter a secret below and click Save first — or use Register in Brevo and a secret will be created automatically.
                            </p>
                        @endif
                    </div>

                    <div>
                        <x-input-label for="brevo_webhook_secret" value="Webhook secret" />
                        <x-text-input
                            id="brevo_webhook_secret"
                            name="brevo_webhook_secret"
                            type="text"
                            class="mt-1 block w-full font-mono text-sm"
                            :value="old('brevo_webhook_secret', $settings['brevo_webhook_secret'] ?? '')"
                            autocomplete="off"
                            placeholder="e.g. a long random string"
                        />
                        <p class="mt-1 text-xs text-sh-mid">Leave blank when saving to keep the existing secret.</p>
                    </div>

                    <div>
                        <button
                            type="submit"
                            form="brevo-register-webhook-form"
                            class="btn-brand text-xs"
                            onclick="return confirm('Register this open-tracking webhook in Brevo now?');"
                        >
                            Register in Brevo
                        </button>
                        <p class="mt-1 text-xs text-sh-mid">Creates a transactional webhook for Opened + Unique opened using your saved Brevo API key.</p>
                    </div>
                </div>

                <div>
                    <x-input-label for="form_base_url" value="Public form base URL" />
                    <x-text-input id="form_base_url" name="form_base_url" type="url" class="mt-1 block w-full" :value="old('form_base_url', $settings['form_base_url'] ?? '')" placeholder="https://www.example.com" />
                    <p class="mt-1 text-xs text-sh-mid">Used in Edit Enquiry Emails. Leave blank to auto-detect from the form server.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="brevo_sender_email" value="Sender email" />
                        <x-text-input id="brevo_sender_email" name="brevo_sender_email" type="email" class="mt-1 block w-full" :value="old('brevo_sender_email', $settings['brevo_sender_email'] ?? '')" />
                    </div>
                    <div>
                        <x-input-label for="brevo_sender_name" value="Sender name" />
                        <x-text-input id="brevo_sender_name" name="brevo_sender_name" type="text" class="mt-1 block w-full" :value="old('brevo_sender_name', $settings['brevo_sender_name'] ?? '')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="brevo_contact_email" value="Contact email" />
                    <x-text-input id="brevo_contact_email" name="brevo_contact_email" type="email" class="mt-1 block w-full" :value="old('brevo_contact_email', $settings['brevo_contact_email'] ?? '')" />
                </div>

                <div class="flex items-center gap-2">
                    <input id="brevo_lead_notification_enabled" name="brevo_lead_notification_enabled" type="checkbox" value="1" class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand" @checked(old('brevo_lead_notification_enabled', filter_var($settings['brevo_lead_notification_enabled'] ?? '1', FILTER_VALIDATE_BOOLEAN)))>
                    <label for="brevo_lead_notification_enabled" class="text-sm text-sh-mid">Send new lead notification emails to the office</label>
                </div>

                <div>
                    <x-input-label for="brevo_office_email" value="Office notification email" />
                    <x-text-input id="brevo_office_email" name="brevo_office_email" type="email" class="mt-1 block w-full" :value="old('brevo_office_email', $settings['brevo_office_email'] ?? 'office@safer-handling.co.uk')" />
                    <p class="mt-1 text-xs text-sh-mid">Receives a new lead email from training@ when an enquiry is submitted.</p>
                </div>

                <div>
                    <x-input-label for="brevo_logo_url" value="Logo URL" />
                    <x-text-input id="brevo_logo_url" name="brevo_logo_url" type="url" class="mt-1 block w-full" :value="old('brevo_logo_url', $settings['brevo_logo_url'] ?? '')" />
                </div>

                <div>
                    <x-input-label for="brevo_quote_accept_url" value="Quote accept URL" />
                    <x-text-input id="brevo_quote_accept_url" name="brevo_quote_accept_url" type="text" class="mt-1 block w-full" :value="old('brevo_quote_accept_url', $settings['brevo_quote_accept_url'] ?? '')" />
                    <p class="mt-1 text-xs text-sh-mid">Leave blank. Accept Quote always uses the booking form (<code>/booking?enquiry=…&amp;token=…</code>), not the enquiry edit form. Only set this to override with another <code>/booking</code> URL.</p>
                </div>
            </div>

            <div class="brand-panel space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-brand-header">Office unreplied auto-reply</h3>
                    <p class="mt-1 text-sm text-sh-mid">
                        When enabled, the scheduler checks the office inbox and sends the <code>email.html</code> template from
                        <strong>office@safer-handling.co.uk</strong> to people who emailed in and have not received a human reply within 8 hours.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <input id="office_auto_reply_enabled" name="office_auto_reply_enabled" type="checkbox" value="1" class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand" @checked(old('office_auto_reply_enabled', filter_var($settings['office_auto_reply_enabled'] ?? '0', FILTER_VALIDATE_BOOLEAN)))>
                    <label for="office_auto_reply_enabled" class="text-sm text-sh-mid">Enable automatic unreplied email responses (after 8 hours)</label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="office_auto_reply_hours" value="Wait hours before auto-reply" />
                        <x-text-input id="office_auto_reply_hours" name="office_auto_reply_hours" type="number" min="1" max="168" class="mt-1 block w-full" :value="old('office_auto_reply_hours', $settings['office_auto_reply_hours'] ?? '8')" />
                    </div>
                    <div>
                        <x-input-label for="office_imap_username" value="IMAP username" />
                        <x-text-input id="office_imap_username" name="office_imap_username" type="email" class="mt-1 block w-full" :value="old('office_imap_username', $settings['office_imap_username'] ?? ($settings['brevo_office_email'] ?? 'office@safer-handling.co.uk'))" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <x-input-label for="office_imap_host" value="IMAP host" />
                        <x-text-input id="office_imap_host" name="office_imap_host" type="text" class="mt-1 block w-full font-mono text-sm" :value="old('office_imap_host', $settings['office_imap_host'] ?? 'outlook.office365.com')" />
                    </div>
                    <div>
                        <x-input-label for="office_imap_port" value="IMAP port" />
                        <x-text-input id="office_imap_port" name="office_imap_port" type="number" class="mt-1 block w-full" :value="old('office_imap_port', $settings['office_imap_port'] ?? '993')" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="office_imap_encryption" value="IMAP encryption" />
                        <select id="office_imap_encryption" name="office_imap_encryption" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand focus:ring-brand">
                            @php $enc = old('office_imap_encryption', $settings['office_imap_encryption'] ?? 'ssl'); @endphp
                            <option value="ssl" @selected($enc === 'ssl')>SSL</option>
                            <option value="tls" @selected($enc === 'tls')>TLS</option>
                            <option value="none" @selected($enc === 'none')>None</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="office_imap_password" value="IMAP password / app password" />
                        <x-text-input id="office_imap_password" name="office_imap_password" type="password" class="mt-1 block w-full font-mono text-sm" :value="old('office_imap_password', '')" autocomplete="new-password" />
                        <p class="mt-1 text-xs text-sh-mid">Leave blank when saving to keep the existing password. Microsoft 365 usually needs an app password if IMAP is allowed.</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="office_imap_inbox_folder" value="Inbox folder" />
                        <x-text-input id="office_imap_inbox_folder" name="office_imap_inbox_folder" type="text" class="mt-1 block w-full" :value="old('office_imap_inbox_folder', $settings['office_imap_inbox_folder'] ?? 'INBOX')" />
                    </div>
                    <div>
                        <x-input-label for="office_imap_sent_folder" value="Sent folder" />
                        <x-text-input id="office_imap_sent_folder" name="office_imap_sent_folder" type="text" class="mt-1 block w-full" :value="old('office_imap_sent_folder', $settings['office_imap_sent_folder'] ?? 'Sent Items')" />
                        <p class="mt-1 text-xs text-sh-mid">Used to detect whether someone from the office has already replied.</p>
                    </div>
                </div>
            </div>

            <div class="brand-panel space-y-4">
                <div>
                    <div class="flex items-center gap-2">
                        <x-xero-badge class="!h-6 !w-6" />
                        <h3 class="text-base font-semibold text-brand-header">Xero quotes</h3>
                    </div>
                    <p class="mt-1 text-sm text-sh-mid">Create a Xero contact and quote using the form total as Including Travel but Excluding VAT (Xero adds VAT on top), download the quote PDF, and email it to the client via Brevo (Xero has no quote-email API). Invoices created from those quotes keep the same exclusive totals.</p>
                </div>

                <div class="flex items-center gap-2">
                    <input id="xero_enabled" name="xero_enabled" type="checkbox" value="1" class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand" @checked(old('xero_enabled', filter_var($settings['xero_enabled'] ?? '0', FILTER_VALIDATE_BOOLEAN)))>
                    <label for="xero_enabled" class="text-sm text-sh-mid">Send quotes through Xero instead of Brevo</label>
                </div>

                <div class="rounded-[12px] border border-sh-border bg-white/70 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-sh-text">
                                {{ $xeroConnected ? 'Connected to Xero' : 'Not connected' }}
                            </p>
                            @if ($xeroConnected && $xeroTokenExpiresAt > 0)
                                <p class="mt-1 text-xs text-sh-mid">Access token refreshes automatically. Expires around {{ \Illuminate\Support\Carbon::createFromTimestamp($xeroTokenExpiresAt)->timezone(config('app.timezone'))->format('d M Y H:i') }}.</p>
                            @else
                                <p class="mt-1 text-xs text-sh-mid">Save your client ID/secret first, then connect your Xero organisation.</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.settings.xero.connect') }}" class="btn-brand text-xs">
                                {{ $xeroConnected ? 'Reconnect Xero' : 'Connect Xero' }}
                            </a>
                            @if ($xeroConnected)
                                <button type="submit" form="xero-disconnect-form" class="btn-brand-outline text-xs">Disconnect</button>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="xero_client_id" value="Client ID" />
                        <x-text-input id="xero_client_id" name="xero_client_id" type="text" class="mt-1 block w-full font-mono text-sm" :value="old('xero_client_id', $settings['xero_client_id'] ?? '')" autocomplete="off" />
                    </div>
                    <div>
                        <x-input-label for="xero_client_secret" value="Client secret" />
                        <x-text-input id="xero_client_secret" name="xero_client_secret" type="password" class="mt-1 block w-full font-mono text-sm" :value="old('xero_client_secret', $settings['xero_client_secret'] ?? '')" autocomplete="off" />
                        <p class="mt-1 text-xs text-sh-mid">Leave blank when saving to keep the existing secret.</p>
                    </div>
                </div>

                <div>
                    <x-input-label for="xero_redirect_uri" value="OAuth redirect URI" />
                    <x-text-input id="xero_redirect_uri" name="xero_redirect_uri" type="url" class="mt-1 block w-full" :value="old('xero_redirect_uri', $settings['xero_redirect_uri'] ?? $xeroRedirectUri)" />
                    <p class="mt-1 text-xs text-sh-mid">
                        This URI must be listed on the <strong>same</strong> Xero app as the Client ID above
                        (<a href="https://developer.xero.com/app/manage" class="underline" target="_blank" rel="noopener">developer portal → Redirect URIs</a>).
                        Copy/paste exactly — no trailing slash. Localhost alone is not enough for production.
                    </p>
                    <p class="mt-2 break-all rounded bg-sh-surface px-2 py-1.5 font-mono text-xs text-sh-ink">{{ $xeroLiveRedirectUri }}</p>
                    @if (trim((string) ($settings['xero_client_secret'] ?? '')) === '')
                        <p class="mt-2 text-xs font-semibold text-red-700">Client secret is empty — paste it from Xero and Save before connecting.</p>
                    @endif
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="xero_tenant_id" value="Tenant / organisation ID" />
                        <x-text-input id="xero_tenant_id" name="xero_tenant_id" type="text" class="mt-1 block w-full font-mono text-sm" :value="old('xero_tenant_id', $settings['xero_tenant_id'] ?? '')" />
                        <p class="mt-1 text-xs text-sh-mid">Filled automatically after connecting.</p>
                    </div>
                    <div>
                        <x-input-label for="xero_default_item_code" value="Default product / item code" />
                        <x-text-input id="xero_default_item_code" name="xero_default_item_code" type="text" class="mt-1 block w-full" :value="old('xero_default_item_code', $settings['xero_default_item_code'] ?? '')" placeholder="e.g. TRAINING" />
                        <p class="mt-1 text-xs text-sh-mid">Matched by Xero item code or name. Quote unit price still comes from the enquiry amount.</p>
                    </div>
                    <div>
                        <x-input-label for="xero_sales_account_code" value="Sales account code" />
                        <x-text-input id="xero_sales_account_code" name="xero_sales_account_code" type="text" class="mt-1 block w-full font-mono text-sm" :value="old('xero_sales_account_code', $settings['xero_sales_account_code'] ?? '200')" placeholder="200" />
                        <p class="mt-1 text-xs text-sh-mid">Assigned to each quote line item (default 200 = Sales).</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="xero_vat_rate" value="VAT rate (%)" />
                        <x-text-input id="xero_vat_rate" name="xero_vat_rate" type="text" class="mt-1 block w-full" :value="old('xero_vat_rate', $settings['xero_vat_rate'] ?? '20')" />
                    </div>
                    <div>
                        <x-input-label for="xero_branding_theme_id" value="Branding theme ID (optional)" />
                        <x-text-input id="xero_branding_theme_id" name="xero_branding_theme_id" type="text" class="mt-1 block w-full font-mono text-sm" :value="old('xero_branding_theme_id', $settings['xero_branding_theme_id'] ?? '')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="xero_webhook_key" value="Webhook key" />
                    <x-text-input id="xero_webhook_key" name="xero_webhook_key" type="password" class="mt-1 block w-full font-mono text-sm" :value="old('xero_webhook_key', $settings['xero_webhook_key'] ?? '')" autocomplete="off" />
                    <p class="mt-1 text-xs text-sh-mid">
                        Paste the signing key exactly as shown in the Xero developer portal → Webhooks
                        (including a leading <code>/</code> if present). Delivery URL must be exactly
                        <code class="break-all">{{ rtrim(config('app.url'), '/') }}/api/xero/webhooks</code>.
                        Intent to receive fails with “Response not 2XX” until this key is saved here (or as
                        <code>XERO_WEBHOOK_KEY</code> in the server env) on the live site. Leave blank when
                        saving to keep the existing key.
                    </p>
                </div>
            </div>

            <div class="brand-panel space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-brand-header">Forge booking intake</h3>
                    <p class="mt-1 text-sm text-sh-mid">When the accept form and venue details are saved, send a full booking snapshot to Forge for admin review. Existing enquiry data is never deleted.</p>
                </div>

                <div class="flex items-center gap-2">
                    <input id="forge_enabled" name="forge_enabled" type="checkbox" value="1" class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand" @checked(old('forge_enabled', filter_var($settings['forge_enabled'] ?? '0', FILTER_VALIDATE_BOOLEAN)))>
                    <label for="forge_enabled" class="text-sm text-sh-mid">Send booking create/edit snapshots to Forge</label>
                </div>

                <div>
                    <x-input-label for="forge_webhook_url" value="Webhook URL" />
                    <x-text-input id="forge_webhook_url" name="forge_webhook_url" type="url" class="mt-1 block w-full font-mono text-sm" :value="old('forge_webhook_url', $settings['forge_webhook_url'] ?? 'https://saferhandling.forgecrm.co.uk/safer_production/webhooks/bookings/')" />
                </div>

                <div>
                    <x-input-label for="forge_webhook_token" value="Webhook token" />
                    <x-text-input id="forge_webhook_token" name="forge_webhook_token" type="password" class="mt-1 block w-full font-mono text-sm" :value="old('forge_webhook_token', $settings['forge_webhook_token'] ?? '')" autocomplete="off" />
                    <p class="mt-1 text-xs text-sh-mid">Sent as <code>X-Webhook-Token</code>. Leave blank when saving to keep the existing token.</p>
                </div>
            </div>

            <div class="flex justify-end">
                <x-primary-button>Save configuration</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
