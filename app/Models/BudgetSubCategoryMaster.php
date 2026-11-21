<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetSubCategoryMaster extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'master_data_ebudget_budget_sub_category_master';
    public $timestamps = false;

    protected $fillable = [
        'category_id',
        'sub_category_code',
        'sub_category_name',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategoryMaster::class, 'category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItemMaster::class, 'sub_category_id');
    }
}
