<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFormSubmission extends Model
{
    protected $fillable = [
        'custom_form_id',
        'first_name',
        'surname',
        'organisation',
        'email',
        'phone',
        'interests',
        'contact_preference',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'interests' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->surname);
    }

    /**
     * @return list<string>
     */
    public function interestLabels(): array
    {
        $options = CustomForm::interestOptions();
        $labels = [];
        foreach ($this->interests ?? [] as $value) {
            $key = (string) $value;
            $labels[] = $options[$key] ?? $key;
        }

        return $labels;
    }

    public function contactPreferenceLabel(): string
    {
        $options = CustomForm::contactPreferenceOptions();
        $key = (string) ($this->contact_preference ?? '');

        return $options[$key] ?? ($key !== '' ? $key : '—');
    }
}
