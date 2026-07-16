<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingMatrixEntry extends Model
{
    protected $fillable = [
        'sector',
        'course',
        'course_value',
        'format',
        'sub_option',
        'min_attendees',
        'max_cap',
        'default_attendees',
        'pricing',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'pricing' => 'array',
            'is_active' => 'boolean',
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
        ];

        if ($this->default_attendees !== null) {
            $row['defaultAttendees'] = $this->default_attendees;
        }

        return $row;
    }
}
