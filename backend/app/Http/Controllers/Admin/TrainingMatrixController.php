<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrainingMatrixEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class TrainingMatrixController extends Controller
{
    public function index(): View
    {
        $editEntry = null;
        if (request('modal') === 'edit' && request()->filled('entry')) {
            $editEntry = TrainingMatrixEntry::query()->find(request('entry'));
        }

        return view('admin.training-matrix.index', [
            'entries' => TrainingMatrixEntry::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->paginate(20),
            'editEntry' => $editEntry,
            'openCreateModal' => request('modal') === 'create',
            'openEditModal' => request('modal') === 'edit' && $editEntry !== null,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.training-matrix.index', ['modal' => 'create']);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()->route('admin.training-matrix.index', ['modal' => 'create'])
                ->withInput()
                ->withErrors($validator);
        }

        $validated = $this->validatedFromRequest($request, $validator->validated());
        $validated['sort_order'] = (int) (TrainingMatrixEntry::query()->max('sort_order') ?? 0) + 1;

        TrainingMatrixEntry::query()->create($validated);

        return redirect()->route('admin.training-matrix.index')->with('status', 'Training matrix entry created.');
    }

    public function edit(TrainingMatrixEntry $trainingMatrix): RedirectResponse
    {
        return redirect()->route('admin.training-matrix.index', [
            'modal' => 'edit',
            'entry' => $trainingMatrix->id,
        ]);
    }

    public function update(Request $request, TrainingMatrixEntry $trainingMatrix): RedirectResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()->route('admin.training-matrix.index', [
                'modal' => 'edit',
                'entry' => $trainingMatrix->id,
            ])->withInput()->withErrors($validator);
        }

        $trainingMatrix->update($this->validatedFromRequest($request, $validator->validated()));

        return redirect()->route('admin.training-matrix.index')->with('status', 'Training matrix entry updated.');
    }

    public function destroy(TrainingMatrixEntry $trainingMatrix): RedirectResponse
    {
        $trainingMatrix->delete();

        return redirect()->route('admin.training-matrix.index')->with('status', 'Training matrix entry deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'sector' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'max:255'],
            'course_value' => ['required', 'string', 'max:255'],
            'format' => ['required', 'string', 'max:255'],
            'sub_option' => ['required', 'string', 'max:255'],
            'min_attendees' => ['required', 'integer', 'min:1', 'max:999'],
            'max_cap' => ['nullable', 'integer', 'min:1', 'max:999'],
            'default_attendees' => ['nullable', 'integer', 'min:1', 'max:999'],
            'pricing_kind' => ['required', 'string', 'in:addonBands,addonBandsLinear,addonBandsPer4621,flat,flatUnlimited,perDelegate'],
            'base_to_12' => ['nullable', 'numeric', 'min:0'],
            'per_13_to_20' => ['nullable', 'numeric', 'min:0'],
            'fixed_21_plus' => ['nullable', 'numeric', 'min:0'],
            'per_after_12' => ['nullable', 'numeric', 'min:0'],
            'per_21_plus' => ['nullable', 'numeric', 'min:0'],
            'flat_amount' => ['nullable', 'numeric', 'min:0'],
            'per_delegate_rate' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function validatedFromRequest(Request $request, array $validated): array
    {
        return [
            'sector' => $validated['sector'],
            'course' => $validated['course'],
            'course_value' => $validated['course_value'],
            'format' => $validated['format'],
            'sub_option' => $validated['sub_option'],
            'min_attendees' => $validated['min_attendees'],
            'max_cap' => $validated['max_cap'] ?? null,
            'default_attendees' => $validated['default_attendees'] ?? null,
            'pricing' => $this->buildPricing($validated),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildPricing(array $validated): array
    {
        $kind = $validated['pricing_kind'];

        return match ($kind) {
            'addonBands' => [
                'kind' => 'addonBands',
                'baseTo12' => (float) ($validated['base_to_12'] ?? 0),
                'per13to20' => (float) ($validated['per_13_to_20'] ?? 0),
                'fixed21Plus' => (float) ($validated['fixed_21_plus'] ?? 0),
            ],
            'addonBandsLinear' => [
                'kind' => 'addonBandsLinear',
                'baseTo12' => (float) ($validated['base_to_12'] ?? 0),
                'perAfter12' => (float) ($validated['per_after_12'] ?? 0),
            ],
            'addonBandsPer4621' => [
                'kind' => 'addonBandsPer4621',
                'baseTo12' => (float) ($validated['base_to_12'] ?? 0),
                'per13to20' => (float) ($validated['per_13_to_20'] ?? 0),
                'per21Plus' => (float) ($validated['per_21_plus'] ?? 0),
            ],
            'flat', 'flatUnlimited' => [
                'kind' => $kind,
                'amount' => (float) ($validated['flat_amount'] ?? 0),
            ],
            'perDelegate' => [
                'kind' => 'perDelegate',
                'rate' => (float) ($validated['per_delegate_rate'] ?? 0),
            ],
            default => ['kind' => $kind],
        };
    }
}
