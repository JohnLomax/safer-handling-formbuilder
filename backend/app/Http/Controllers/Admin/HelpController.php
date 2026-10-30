<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(): View
    {
        $settings = Setting::allCached();
        $unitPrice = trim((string) ($settings['additional_attendee_unit_price'] ?? '80'));
        if ($unitPrice === '' || ! is_numeric($unitPrice)) {
            $unitPrice = '80';
        }

        return view('admin.help.index', [
            'attendeesApiUrl' => rtrim((string) config('app.url'), '/').'/api/enquiries/{id}/attendees',
            'attendeesApiTokenConfigured' => trim((string) ($settings['enquiry_api_token'] ?? '')) !== '',
            'additionalAttendeeUnitPrice' => number_format((float) $unitPrice, 2, '.', ''),
        ]);
    }
}
