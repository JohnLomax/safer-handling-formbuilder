<?php

namespace Database\Seeders;

use App\Models\TrainingMatrixEntry;
use Illuminate\Database\Seeder;

class TrainingMatrixSeeder extends Seeder
{
    public function run(): void
    {
        if (TrainingMatrixEntry::query()->exists()) {
            return;
        }

        $rows = [
            [
                'sector' => 'Education and Residential',
                'course' => 'Safer Physical Intervention Training',
                'course_value' => 'Safer Physical Intervention Training FF',
                'format' => 'Face to face',
                'sub_option' => 'Full 1 Day Course',
                'min_attendees' => 1,
                'max_cap' => null,
                'default_attendees' => null,
                'pricing' => ['kind' => 'addonBands', 'baseTo12' => 995, 'per13to20' => 45, 'fixed21Plus' => 1248],
            ],
            [
                'sector' => 'Education and Residential',
                'course' => 'Safer Physical Intervention Training',
                'course_value' => 'Safer Physical Intervention Training FT',
                'format' => 'Face to face',
                'sub_option' => 'Twilight(s)',
                'min_attendees' => 1,
                'max_cap' => null,
                'default_attendees' => null,
                'pricing' => ['kind' => 'addonBands', 'baseTo12' => 1248, 'per13to20' => 45, 'fixed21Plus' => 1248],
            ],
            [
                'sector' => 'Education and Residential',
                'course' => 'Safer Physical Intervention Training',
                'course_value' => 'Safer Physical Intervention Training BT',
                'format' => 'Blended',
                'sub_option' => 'Twilight',
                'min_attendees' => 1,
                'max_cap' => null,
                'default_attendees' => null,
                'pricing' => ['kind' => 'addonBands', 'baseTo12' => 995, 'per13to20' => 45, 'fixed21Plus' => 1248],
            ],
            [
                'sector' => 'Education and Residential',
                'course' => 'Safer Physical Intervention Training',
                'course_value' => 'Safer Physical Intervention Training BD',
                'format' => 'Blended',
                'sub_option' => 'Daytime - 1.5-2.5 Hours',
                'min_attendees' => 1,
                'max_cap' => null,
                'default_attendees' => null,
                'pricing' => ['kind' => 'addonBands', 'baseTo12' => 995, 'per13to20' => 45, 'fixed21Plus' => 1248],
            ],
            [
                'sector' => 'Education and Residential',
                'course' => 'Combined Positive Behaviour Support & Safer physical intervention strategies',
                'course_value' => '2 Day PBS + Physical Int',
                'format' => 'Face To Face',
                'sub_option' => 'Full 2 Day Course',
                'min_attendees' => 1,
                'max_cap' => 120,
                'default_attendees' => null,
                'pricing' => ['kind' => 'addonBandsLinear', 'baseTo12' => 1990, 'perAfter12' => 45],
            ],
            [
                'sector' => 'Education and Residential',
                'course' => 'Combined Positive Behaviour Support & Safer physical intervention strategies',
                'course_value' => '2 Day PBS + Physical Int 4',
                'format' => 'Face To Face',
                'sub_option' => '4 Twilights',
                'min_attendees' => 1,
                'max_cap' => 120,
                'default_attendees' => null,
                'pricing' => ['kind' => 'addonBandsLinear', 'baseTo12' => 2400, 'perAfter12' => 45],
            ],
            [
                'sector' => 'Education and Residential',
                'course' => 'Combined Positive Behaviour Support & Safer physical intervention strategies',
                'course_value' => 'Positive Behav Support, Physical Int & Legal Brief',
                'format' => 'Blended',
                'sub_option' => '2 Twilights / Daytime 1.5.2.5',
                'min_attendees' => 1,
                'max_cap' => 120,
                'default_attendees' => null,
                'pricing' => ['kind' => 'addonBandsLinear', 'baseTo12' => 1248, 'perAfter12' => 45],
            ],
            [
                'sector' => 'Education and Residential',
                'course' => 'Positive Behaviour Support',
                'course_value' => 'Positive Behaviour Support F',
                'format' => 'Face to face',
                'sub_option' => 'Full 1 Day Course',
                'min_attendees' => 1,
                'max_cap' => 120,
                'default_attendees' => null,
                'pricing' => ['kind' => 'addonBandsLinear', 'baseTo12' => 1200, 'perAfter12' => 45],
            ],
            [
                'sector' => 'Education and Residential',
                'course' => 'Positive Behaviour Support',
                'course_value' => 'Positive Behaviour Support F2T',
                'format' => 'Face to face',
                'sub_option' => '2 Twilights',
                'min_attendees' => 1,
                'max_cap' => 120,
                'default_attendees' => null,
                'pricing' => ['kind' => 'addonBandsLinear', 'baseTo12' => 1200, 'perAfter12' => 45],
            ],
            [
                'sector' => 'Education and Residential',
                'course' => 'Positive Behaviour Support & Safer Physical Intervention Train the Trainer Award',
                'course_value' => 'PBS & Safer Physical Intervention TTT O',
                'format' => 'Face To Face',
                'sub_option' => '2 Days',
                'min_attendees' => 3,
                'max_cap' => 120,
                'default_attendees' => 3,
                'pricing' => ['kind' => 'perDelegate', 'rate' => 1662.5],
            ],
        ];

        foreach ($rows as $index => $row) {
            TrainingMatrixEntry::query()->create([
                ...$row,
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }
    }
}
