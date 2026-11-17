<?php

namespace App\Http\Controllers;

use App\Models\CustomForm;
use App\Models\CustomFormSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomFormPublicController extends Controller
{
    public function show(string $slug): View
    {
        $form = CustomForm::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return view('custom-forms.public', [
            'form' => $form,
            'interestOptions' => CustomForm::interestOptions(),
            'contactOptions' => CustomForm::contactPreferenceOptions(),
            'submitted' => false,
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse|View
    {
        $form = CustomForm::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $interestKeys = array_keys(CustomForm::interestOptions());
        $contactKeys = array_keys(CustomForm::contactPreferenceOptions());

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'surname' => ['required', 'string', 'max:120'],
            'organisation' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', 'in:'.implode(',', $interestKeys)],
            'contact_preference' => ['nullable', 'string', 'in:'.implode(',', $contactKeys)],
        ], [
            'first_name.required' => 'Please enter your first name.',
            'surname.required' => 'Please enter your surname.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $interests = array_values(array_unique(array_map('strval', $validated['interests'] ?? [])));

        CustomFormSubmission::query()->create([
            'custom_form_id' => $form->id,
            'first_name' => trim((string) $validated['first_name']),
            'surname' => trim((string) $validated['surname']),
            'organisation' => trim((string) ($validated['organisation'] ?? '')) ?: null,
            'email' => trim((string) $validated['email']),
            'phone' => trim((string) ($validated['phone'] ?? '')) ?: null,
            'interests' => $interests !== [] ? $interests : null,
            'contact_preference' => $validated['contact_preference'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
        ]);

        return view('custom-forms.public', [
            'form' => $form,
            'interestOptions' => CustomForm::interestOptions(),
            'contactOptions' => CustomForm::contactPreferenceOptions(),
            'submitted' => true,
        ]);
    }
}
