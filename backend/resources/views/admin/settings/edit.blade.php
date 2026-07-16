<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header title="Integration settings" description="These values are stored in the shared settings database and used by the public enquiry form. Optional server env vars (MONDAY_*, BREVO_*, etc.) override the database when set." />
    </x-slot>

    <div class="admin-shell">
        @include('admin.partials.alerts')

        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
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
                    <p class="mt-1 text-xs text-sh-mid">Optional. Use <code>@{{email}}</code> as a placeholder for the recipient email.</p>
                </div>
            </div>

            <div class="brand-panel space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-brand-header">Xero quotes</h3>
                    <p class="mt-1 text-sm text-sh-mid">Create a Xero contact and quote using the form total as VAT-inclusive, download the quote PDF, and email it to the client via Brevo (Xero has no quote-email API).</p>
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
                                <form method="POST" action="{{ route('admin.settings.xero.disconnect') }}">
                                    @csrf
                                    <button type="submit" class="btn-brand-outline text-xs">Disconnect</button>
                                </form>
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
                        Connect uses the host you’re on now. Add this <strong>exact</strong> URI under
                        Redirect URIs in the
                        <a href="https://developer.xero.com/app/manage" class="underline" target="_blank" rel="noopener">Xero developer portal</a>
                        (scheme, host, and path must match — no trailing slash):
                    </p>
                    <p class="mt-2 break-all rounded bg-sh-surface px-2 py-1.5 font-mono text-xs text-sh-ink">{{ $xeroLiveRedirectUri }}</p>
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
            </div>

            <div class="flex justify-end">
                <x-primary-button>Save configuration</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
