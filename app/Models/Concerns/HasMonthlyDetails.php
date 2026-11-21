<?php

namespace App\Models\Concerns;

trait HasMonthlyDetails
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public static array $monthMapRev = [
        1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
        5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dec',
    ];

    public static array $monthMap = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
        'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
        'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    public function getMonthlyDataDictAttribute(): array
    {
        $res = [];
        $details = $this->relationLoaded('monthlyDetails')
            ? $this->monthlyDetails
            : $this->monthlyDetails()->get();

        foreach ($details as $detail) {
            $mStr = self::$monthMapRev[$detail->month] ?? null;
            if ($mStr) {
                $res[$mStr] = [
                    'headcount' => (float) $detail->headcount,
                    'cost' => (float) $detail->cost,
                    'salary' => (float) ($detail->salary ?? 0),
                    'position_allowance' => (float) ($detail->position_allowance ?? 0),
                    'new_salary' => (float) ($detail->new_salary ?? 0),
                    'new_allowance' => (float) ($detail->new_allowance ?? 0),
                    'cumulative_depreciation' => (float) ($detail->cumulative_depreciation ?? 0),
                ];
            }
        }

        return $res;
    }
}
