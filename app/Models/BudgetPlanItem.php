<?php

namespace App\Models;

use App\Models\Concerns\HasMonthlyDetails;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetPlanItem extends Model
{
    use HasMonthlyDetails;

    protected $connection = 'pgsql';
    protected $table = 'ebudget_budget_plan_item';

    public const CREATED_AT = 'create_date';
    public const UPDATED_AT = 'modify_date';
    protected $dateFormat = 'Y-m-d H:i:s';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $fillable = [
        'budget_category',
        'item_code',
        'general_ledger_code',
        'description',
        'budget_year',
        'base_branch_id',
        'department_id',
        'cost_center_name',
        'document_no',
        'status',
        'cancel_reason',
        'create_eid',
        'modify_eid',
    ];

    protected $casts = [
        'budget_year' => 'integer',
        'create_date' => 'datetime:Y-m-d H:i:s',
        'modify_date' => 'datetime:Y-m-d H:i:s',
    ];

    public function monthlyDetails(): HasMany
    {
        return $this->hasMany(BudgetPlanMonthlyDetail::class, 'plan_item_id');
    }

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
                    'amount' => (float) $detail->amount,
                ];
            }
        }

        return $res;
    }
}
