<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Voucher;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VoucherController extends Controller
{
    public function index(): View
    {
        $editVoucher = null;
        if (request('modal') === 'edit' && request()->filled('voucher')) {
            $editVoucher = Voucher::query()->find(request('voucher'));
        }

        $vouchers = Voucher::query()
            ->withCount('enquiries')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.vouchers.index', [
            'vouchers' => $vouchers,
            'editVoucher' => $editVoucher,
            'openCreateModal' => request('modal') === 'create',
            'openEditModal' => request('modal') === 'edit' && $editVoucher !== null,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.vouchers.index', ['modal' => 'create']);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()->route('admin.vouchers.index', ['modal' => 'create'])
                ->withInput()
                ->withErrors($validator);
        }

        $voucher = Voucher::query()->create($this->validatedFromRequest($request, $validator->validated()));

        ActivityLogger::log(
            action: 'create',
            description: 'Created voucher '.$voucher->code.' (£'.number_format((float) $voucher->amount, 2).')',
            subjectType: Voucher::class,
            subjectId: (int) $voucher->id,
            properties: [
                'area' => 'Vouchers',
                'code' => $voucher->code,
                'amount' => (float) $voucher->amount,
            ],
        );

        return redirect()->route('admin.vouchers.index')->with('status', 'Voucher created.');
    }

    public function show(Voucher $voucher): View
    {
        $voucher->load(['enquiries' => function ($query): void {
            $query->orderByDesc('id');
        }]);

        return view('admin.vouchers.show', [
            'voucher' => $voucher,
            'enquiries' => $voucher->enquiries()->orderByDesc('id')->paginate(25),
        ]);
    }

    public function edit(Voucher $voucher): RedirectResponse
    {
        return redirect()->route('admin.vouchers.index', [
            'modal' => 'edit',
            'voucher' => $voucher->id,
        ]);
    }

    public function update(Request $request, Voucher $voucher): RedirectResponse
    {
        $validator = Validator::make($request->all(), $this->rules($voucher));

        if ($validator->fails()) {
            return redirect()->route('admin.vouchers.index', [
                'modal' => 'edit',
                'voucher' => $voucher->id,
            ])->withInput()->withErrors($validator);
        }

        $voucher->update($this->validatedFromRequest($request, $validator->validated()));

        ActivityLogger::log(
            action: 'update',
            description: 'Updated voucher '.$voucher->code.' (£'.number_format((float) $voucher->amount, 2).')',
            subjectType: Voucher::class,
            subjectId: (int) $voucher->id,
            properties: [
                'area' => 'Vouchers',
                'code' => $voucher->code,
                'amount' => (float) $voucher->amount,
            ],
        );

        return redirect()->route('admin.vouchers.index')->with('status', 'Voucher updated.');
    }

    public function destroy(Voucher $voucher): RedirectResponse
    {
        $code = $voucher->code;
        $amount = (float) $voucher->amount;
        $id = (int) $voucher->id;

        // Keep enquiry history; just detach FK before delete.
        Enquiry::query()->where('voucher_id', $id)->update(['voucher_id' => null]);
        $voucher->delete();

        ActivityLogger::log(
            action: 'delete',
            description: 'Deleted voucher '.$code.' (£'.number_format($amount, 2).')',
            subjectType: Voucher::class,
            subjectId: $id,
            properties: [
                'area' => 'Vouchers',
                'code' => $code,
                'amount' => $amount,
            ],
        );

        return redirect()->route('admin.vouchers.index')->with('status', 'Voucher deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?Voucher $voucher = null): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('vouchers', 'code')->ignore($voucher?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'is_active' => ['nullable', 'boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function validatedFromRequest(Request $request, array $validated): array
    {
        return [
            'code' => Voucher::normalizeCode((string) $validated['code']),
            'name' => trim((string) $validated['name']),
            'amount' => round((float) $validated['amount'], 2),
            'is_active' => $request->boolean('is_active'),
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'max_redemptions' => isset($validated['max_redemptions']) && $validated['max_redemptions'] !== ''
                ? (int) $validated['max_redemptions']
                : null,
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
        ];
    }
}
