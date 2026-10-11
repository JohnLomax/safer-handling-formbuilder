<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Voucher extends Model
{
    protected $fillable = [
        'code',
        'name',
        'amount',
        'is_active',
        'valid_from',
        'valid_until',
        'max_redemptions',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'max_redemptions' => 'integer',
        ];
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
    }

    public function redemptionCount(): int
    {
        return $this->enquiries()->whereNotNull('voucher_id')->count();
    }

    public function isCurrentlyValid(?Carbon $at = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $at ??= now();

        if ($this->valid_from && $at->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $at->gt($this->valid_until)) {
            return false;
        }

        if ($this->max_redemptions !== null && $this->redemptionCount() >= (int) $this->max_redemptions) {
            return false;
        }

        return true;
    }

    public function statusLabel(): string
    {
        if (! $this->is_active) {
            return 'Inactive';
        }
        if ($this->valid_from && now()->lt($this->valid_from)) {
            return 'Scheduled';
        }
        if ($this->valid_until && now()->gt($this->valid_until)) {
            return 'Expired';
        }
        if ($this->max_redemptions !== null && $this->redemptionCount() >= (int) $this->max_redemptions) {
            return 'Fully used';
        }

        return 'Active';
    }
}
