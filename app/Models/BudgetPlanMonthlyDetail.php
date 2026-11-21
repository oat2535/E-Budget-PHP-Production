<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetPlanMonthlyDetail extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'ebudget_budget_plan_monthly_detail';
    public $timestamps = false;

    protected $fillable = [
        'month',
        'amount',
        'plan_item_id',
    ];

    protected $casts = [
        'month' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function planItem(): BelongsTo
    {
        return $this->belongsTo(BudgetPlanItem::class, 'plan_item_id');
    }
}
