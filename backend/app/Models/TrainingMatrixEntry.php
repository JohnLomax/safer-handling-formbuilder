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
        'default_attendees',
        'pricing',
        'is_active',
        'kajabi_enabled',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'kajabi_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'pricing' => 'array',
            'is_active' => 'boolean',
            'kajabi_enabled' => 'boolean',
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
        ];

        $description = trim((string) ($this->course_description ?? ''));
        if ($description !== '') {
            $row['courseDescription'] = $description;
        }

        if ($this->default_attendees !== null) {
            $row['defaultAttendees'] = $this->default_attendees;
        }

        return $row;
    }
}
