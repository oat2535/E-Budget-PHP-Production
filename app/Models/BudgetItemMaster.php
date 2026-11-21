<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetItemMaster extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'master_data_ebudget_budget_item_master';
    public $timestamps = false;

    protected $fillable = [
        'general_ledger_code',
        'category_id',
        'sub_category_id',
        'item_name',
        'salary',
        'estimated_shift_allowance',
        'position_allowance',
        'total_salary',
        'purchase_price',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'estimated_shift_allowance' => 'decimal:2',
        'position_allowance' => 'decimal:2',
        'total_salary' => 'decimal:2',
        'purchase_price' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategoryMaster::class, 'category_id');
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(BudgetSubCategoryMaster::class, 'sub_category_id');
    }
}
