<?php

namespace App\Models;

use App\Models\Concerns\HasMonthlyDetails;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PositionAdjustment extends Model
{
    use HasMonthlyDetails;

    protected $connection = 'pgsql';
    protected $table = 'ebudget_position_adjustment';

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
        'adjustment_type',
        'old_position_name',
        'old_description',
        'new_position_name',
        'new_description',
        'old_salary',
        'old_allowance',
        'new_salary',
        'new_allowance',
        'base_branch_id',
        'department_id',
        'cost_center_name',
        'document_no',
        'item_master_id',
        'salary_general_ledger_code',
        'allowance_general_ledger_code',
        'budget_year',
        'status',
        'cancel_reason',
        'create_eid',
        'modify_eid',
    ];

    protected $casts = [
        'old_salary' => 'decimal:2',
        'old_allowance' => 'decimal:2',
        'new_salary' => 'decimal:2',
        'new_allowance' => 'decimal:2',
        'budget_year' => 'integer',
        'create_date' => 'datetime:Y-m-d H:i:s',
        'modify_date' => 'datetime:Y-m-d H:i:s',
    ];

    public function itemMaster(): BelongsTo
    {
        return $this->belongsTo(BudgetItemMaster::class, 'item_master_id');
    }

    public function monthlyDetails(): HasMany
    {
        return $this->hasMany(BudgetMonthlyDetail::class, 'position_adj_id');
    }
}
