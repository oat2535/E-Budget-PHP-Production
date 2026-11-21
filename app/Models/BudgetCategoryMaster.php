<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetCategoryMaster extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'master_data_ebudget_budget_category_master';
    public $timestamps = false;

    protected $fillable = [
        'category_code',
        'category_name',
    ];

    public function subCategories(): HasMany
    {
        return $this->hasMany(BudgetSubCategoryMaster::class, 'category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItemMaster::class, 'category_id');
    }

    public function generalLedgers(): HasMany
    {
        return $this->hasMany(GeneralLedgerMaster::class, 'category_id');
    }
}
