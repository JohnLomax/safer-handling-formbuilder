<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingMatrixEntry extends Model
{
    protected $fillable = [
        'sector',
        'course',
        'course_value',
        'course_description',
        'format',
        'sub_option',
        'min_attendees',
        'max_cap',
        'max_cap_description',
        'default_attendees',
        'pricing',
        'is_active',
        'kajabi_enabled',
        'enable_second_date',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'kajabi_enabled' => true,
        'enable_second_date' => false,
    ];

    protected function casts(): array
    {
        return [
            'pricing' => 'array',
            'is_active' => 'boolean',
            'kajabi_enabled' => 'boolean',
            'enable_second_date' => 'boolean',
            'min_attendees' => 'integer',
            'max_cap' => 'integer',
            'default_attendees' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toMatrixRow(): array
    {
        $row = [
            'sector' => $this->sector,
            'course' => $this->course,
            'courseValue' => $this->course_value,
            'format' => $this->format,
            'subOption' => $this->sub_option,
            'minAttendees' => $this->min_attendees,
            'maxCap' => $this->max_cap,
            'pricing' => $this->pricing ?? [],
            'kajabiEnabled' => (bool) ($this->kajabi_enabled ?? true),
            'enableSecondDate' => (bool) ($this->enable_second_date ?? false),
        ];

        $description = trim((string) ($this->course_description ?? ''));
        if ($description !== '') {
            $row['courseDescription'] = $description;
        }

        $maxCapDescription = trim((string) ($this->max_cap_description ?? ''));
        if ($maxCapDescription !== '') {
            $row['maxCapDescription'] = $maxCapDescription;
        }

        if ($this->default_attendees !== null) {
            $row['defaultAttendees'] = $this->default_attendees;
        }

        return $row;
    }
}
