<?php

namespace App\Models;

use App\Models\Concerns\InterpretsUtcTimestamps;
use Illuminate\Database\Eloquent\Model;

class FeedbackSubmission extends Model
{
    use InterpretsUtcTimestamps;

    public $timestamps = false;

    protected $table = 'feedback_submissions';

    protected $fillable = [
        'issue_faced',
        'description',
        'resolved_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function statusLabel(): string
    {
        return $this->isResolved() ? 'Resolved' : 'Open';
    }
}
