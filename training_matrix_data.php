<?php
declare(strict_types=1);

require_once __DIR__ . '/database_bridge.php';

/**
 * Organisation training course matrix (pricing, formats, attendee limits).
 * Loads from the shared admin database when available, otherwise uses built-in defaults.
 *
 * @return list<array<string, mixed>>
 */
function trainingMatrixDefaults(): array
{
    return [
        [
            'sector' => 'Education and Residential',
            'course' => 'Safer Physical Intervention Training',
            'courseValue' => 'Safer Physical Intervention Training FF',
            'format' => 'Face to face',
            'subOption' => 'Full 1 Day Course',
            'minAttendees' => 1,
            'maxCap' => null,
            'pricing' => ['kind' => 'addonBands', 'baseTo12' => 995, 'per13to20' => 45, 'fixed21Plus' => 1248],
        ],
        [
            'sector' => 'Education and Residential',
            'course' => 'Safer Physical Intervention Training',
            'courseValue' => 'Safer Physical Intervention Training FT',
            'format' => 'Face to face',
            'subOption' => 'Twilight(s)',
            'minAttendees' => 1,
            'maxCap' => null,
            'pricing' => ['kind' => 'addonBands', 'baseTo12' => 1248, 'per13to20' => 45, 'fixed21Plus' => 1248],
        ],
        [
            'sector' => 'Education and Residential',
            'course' => 'Safer Physical Intervention Training',
            'courseValue' => 'Safer Physical Intervention Training BT',
            'format' => 'Blended',
            'subOption' => 'Twilight',
            'minAttendees' => 1,
            'maxCap' => null,
            'pricing' => ['kind' => 'addonBands', 'baseTo12' => 995, 'per13to20' => 45, 'fixed21Plus' => 1248],
        ],
        [
            'sector' => 'Education and Residential',
            'course' => 'Safer Physical Intervention Training',
            'courseValue' => 'Safer Physical Intervention Training BD',
            'format' => 'Blended',
            'subOption' => 'Daytime - 1.5-2.5 Hours',
            'minAttendees' => 1,
            'maxCap' => null,
            'pricing' => ['kind' => 'addonBands', 'baseTo12' => 995, 'per13to20' => 45, 'fixed21Plus' => 1248],
        ],
        [
            'sector' => 'Education and Residential',
            'course' => 'Combined Positive Behaviour Support & Safer physical intervention strategies',
            'courseValue' => '2 Day PBS + Physical Int',
            'format' => 'Face To Face',
            'subOption' => 'Full 2 Day Course',
            'minAttendees' => 1,
            'maxCap' => 120,
            'pricing' => ['kind' => 'addonBandsLinear', 'baseTo12' => 1990, 'perAfter12' => 45],
        ],
        [
            'sector' => 'Education and Residential',
            'course' => 'Combined Positive Behaviour Support & Safer physical intervention strategies',
            'courseValue' => '2 Day PBS + Physical Int 4',
            'format' => 'Face To Face',
            'subOption' => '4 Twilights',
            'minAttendees' => 1,
            'maxCap' => 120,
            'pricing' => ['kind' => 'addonBandsLinear', 'baseTo12' => 2400, 'perAfter12' => 45],
        ],
        [
            'sector' => 'Education and Residential',
            'course' => 'Combined Positive Behaviour Support & Safer physical intervention strategies',
            'courseValue' => 'Positive Behav Support, Physical Int & Legal Brief',
            'format' => 'Blended',
            'subOption' => '2 Twilights / Daytime 1.5.2.5',
            'minAttendees' => 1,
            'maxCap' => 120,
            'pricing' => ['kind' => 'addonBandsLinear', 'baseTo12' => 1248, 'perAfter12' => 45],
        ],
        [
            'sector' => 'Education and Residential',
            'course' => 'Positive Behaviour Support',
            'courseValue' => 'Positive Behaviour Support F',
            'format' => 'Face to face',
            'subOption' => 'Full 1 Day Course',
            'minAttendees' => 1,
            'maxCap' => 120,
            'pricing' => ['kind' => 'addonBandsLinear', 'baseTo12' => 1200, 'perAfter12' => 45],
        ],
        [
            'sector' => 'Education and Residential',
            'course' => 'Positive Behaviour Support',
            'courseValue' => 'Positive Behaviour Support F2T',
            'format' => 'Face to face',
            'subOption' => '2 Twilights',
            'minAttendees' => 1,
            'maxCap' => 120,
            'pricing' => ['kind' => 'addonBandsLinear', 'baseTo12' => 1200, 'perAfter12' => 45],
        ],
        [
            'sector' => 'Education and Residential',
            'course' => 'Positive Behaviour Support & Safer Physical Intervention Train the Trainer Award',
            'courseValue' => 'PBS & Safer Physical Intervention TTT O',
            'format' => 'Face To Face',
            'subOption' => '2 Days',
            'minAttendees' => 3,
            'maxCap' => 120,
            'defaultAttendees' => 3,
            'pricing' => ['kind' => 'perDelegate', 'rate' => 1662.5],
        ],
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function trainingMatrix(): array
{
    $rows = appTrainingMatrixRows();

    return $rows !== [] ? $rows : trainingMatrixDefaults();
}
