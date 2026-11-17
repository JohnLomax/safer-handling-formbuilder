<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CustomForm extends Model
{
    public const DEFAULT_COURSES_URL = 'https://www.safer-handling.co.uk/';

    /**
     * Interest checkbox options shown on the public form (value => label).
     *
     * @return array<string, string>
     */
    public static function interestOptions(): array
    {
        return [
            'physical_intervention' => 'Physical Intervention Training',
            'train_the_trainer' => 'Train the Trainer',
            'positive_behaviour_support' => 'Positive Behaviour Support',
            'legal_briefing' => 'Legal Briefing / Reasonable Force',
            'bespoke' => 'Bespoke Training',
            'not_sure' => 'Not sure — please contact me',
        ];
    }

    /**
     * Follow-up preference options (value => label).
     *
     * @return array<string, string>
     */
    public static function contactPreferenceOptions(): array
    {
        return [
            'yes' => 'Yes please',
            'information_only' => 'Just send me the information',
        ];
    }

    protected $fillable = [
        'name',
        'slug',
        'title',
        'intro',
        'courses_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CustomFormSubmission::class);
    }

    public static function normalizeSlug(string $slug): string
    {
        $slug = Str::slug(trim($slug));

        return $slug !== '' ? $slug : 'form';
    }

    public function publicPath(): string
    {
        return '/f/'.$this->slug;
    }

    public function publicUrl(?string $baseUrl = null): string
    {
        $base = rtrim((string) ($baseUrl
            ?: Setting::getValue('form_base_url', '')
            ?: config('app.url')), '/');

        return $base.$this->publicPath();
    }

    public function coursesLink(): string
    {
        $url = trim((string) ($this->courses_url ?: ''));

        return $url !== '' ? $url : self::DEFAULT_COURSES_URL;
    }

    public function statusLabel(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }
}
