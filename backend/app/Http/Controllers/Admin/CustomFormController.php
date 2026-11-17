<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomForm;
use App\Models\CustomFormSubmission;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomFormController extends Controller
{
    public function index(): View
    {
        $editForm = null;
        if (request('modal') === 'edit' && request()->filled('form')) {
            $editForm = CustomForm::query()->find(request('form'));
        }

        $forms = CustomForm::query()
            ->withCount('submissions')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.custom-forms.index', [
            'forms' => $forms,
            'editForm' => $editForm,
            'openCreateModal' => request('modal') === 'create',
            'openEditModal' => request('modal') === 'edit' && $editForm !== null,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.custom-forms.index', ['modal' => 'create']);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()->route('admin.custom-forms.index', ['modal' => 'create'])
                ->withInput()
                ->withErrors($validator);
        }

        $form = CustomForm::query()->create($this->validatedFromRequest($request, $validator->validated()));

        ActivityLogger::log(
            action: 'create',
            description: 'Created custom form '.$form->name.' (/f/'.$form->slug.')',
            subjectType: CustomForm::class,
            subjectId: (int) $form->id,
            properties: [
                'area' => 'Custom Forms',
                'slug' => $form->slug,
            ],
        );

        return redirect()
            ->route('admin.custom-forms.show', $form)
            ->with('status', 'Custom form created. Share the QR code or public link.');
    }

    public function show(Request $request, CustomForm $customForm): View
    {
        $search = trim((string) $request->query('q', ''));
        $followUp = $this->normalizeFollowUpFilter($request->query('follow_up'));

        $submissions = $this->submissionsQuery($customForm, $search, $followUp)
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.custom-forms.show', [
            'form' => $customForm,
            'submissions' => $submissions,
            'publicUrl' => $customForm->publicUrl(),
            'search' => $search,
            'followUpFilter' => $followUp,
            'totalSubmissions' => $customForm->submissions()->count(),
            'followUpYesCount' => $customForm->submissions()->where('contact_preference', 'yes')->count(),
            'followUpInfoCount' => $customForm->submissions()->where('contact_preference', 'information_only')->count(),
        ]);
    }

    public function edit(CustomForm $customForm): RedirectResponse
    {
        return redirect()->route('admin.custom-forms.index', [
            'modal' => 'edit',
            'form' => $customForm->id,
        ]);
    }

    public function update(Request $request, CustomForm $customForm): RedirectResponse
    {
        $validator = Validator::make($request->all(), $this->rules($customForm));

        if ($validator->fails()) {
            return redirect()->route('admin.custom-forms.index', [
                'modal' => 'edit',
                'form' => $customForm->id,
            ])->withInput()->withErrors($validator);
        }

        $customForm->update($this->validatedFromRequest($request, $validator->validated()));

        ActivityLogger::log(
            action: 'update',
            description: 'Updated custom form '.$customForm->name.' (/f/'.$customForm->slug.')',
            subjectType: CustomForm::class,
            subjectId: (int) $customForm->id,
            properties: [
                'area' => 'Custom Forms',
                'slug' => $customForm->slug,
            ],
        );

        return redirect()->route('admin.custom-forms.show', $customForm)->with('status', 'Custom form updated.');
    }

    public function destroy(CustomForm $customForm): RedirectResponse
    {
        $name = $customForm->name;
        $slug = $customForm->slug;
        $id = (int) $customForm->id;
        $customForm->delete();

        ActivityLogger::log(
            action: 'delete',
            description: 'Deleted custom form '.$name.' (/f/'.$slug.')',
            subjectType: CustomForm::class,
            subjectId: $id,
            properties: [
                'area' => 'Custom Forms',
                'slug' => $slug,
            ],
        );

        return redirect()->route('admin.custom-forms.index')->with('status', 'Custom form deleted.');
    }

    public function export(Request $request, CustomForm $customForm): StreamedResponse|Response
    {
        $search = trim((string) $request->query('q', ''));
        $followUp = $this->normalizeFollowUpFilter($request->query('follow_up'));
        $items = $this->submissionsQuery($customForm, $search, $followUp)
            ->orderByDesc('id')
            ->get();
        $filename = 'custom-form-'.$customForm->slug.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($items): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID',
                'First name',
                'Surname',
                'Organisation',
                'Email',
                'Phone',
                'Interests',
                'Contact preference',
                'Submitted at',
            ]);

            foreach ($items as $item) {
                /** @var CustomFormSubmission $item */
                fputcsv($handle, [
                    $item->id,
                    $item->first_name,
                    $item->surname,
                    $item->organisation,
                    $item->email,
                    $item->phone,
                    implode('; ', $item->interestLabels()),
                    $item->contactPreferenceLabel(),
                    $item->created_at?->format('Y-m-d H:i:s') ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\CustomFormSubmission>
     */
    private function submissionsQuery(CustomForm $form, string $search, string $followUp)
    {
        $query = CustomFormSubmission::query()->where('custom_form_id', $form->id);

        if ($followUp === 'yes' || $followUp === 'information_only') {
            $query->where('contact_preference', $followUp);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('first_name', 'like', $like)
                    ->orWhere('surname', 'like', $like)
                    ->orWhere('organisation', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        return $query;
    }

    private function normalizeFollowUpFilter(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return in_array($value, ['yes', 'information_only'], true) ? $value : 'all';
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?CustomForm $form = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('custom_forms', 'slug')->ignore($form?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'intro' => ['nullable', 'string', 'max:2000'],
            'courses_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function validatedFromRequest(Request $request, array $validated): array
    {
        return [
            'name' => trim((string) $validated['name']),
            'slug' => CustomForm::normalizeSlug((string) $validated['slug']),
            'title' => trim((string) $validated['title']),
            'intro' => trim((string) ($validated['intro'] ?? '')) ?: null,
            'courses_url' => trim((string) ($validated['courses_url'] ?? '')) ?: null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
