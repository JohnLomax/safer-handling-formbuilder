<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Form/PDO helpers store naive datetimes in UTC (gmdate).
 * Interpret those as UTC and expose them in the app timezone (Europe/London).
 */
trait InterpretsUtcTimestamps
{
    protected function asDateTime($value)
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc()->timezone(config('app.timezone'));
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value))
                ->utc()
                ->timezone(config('app.timezone'));
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value, 'UTC')
                ->timezone(config('app.timezone'));
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }

            return Carbon::parse($value, 'UTC')->timezone(config('app.timezone'));
        }

        return parent::asDateTime($value)?->timezone(config('app.timezone'));
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)->utc()->format('Y-m-d H:i:s');
    }
}
