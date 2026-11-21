<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralLedgerMaster extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'master_data_ebudget_general_ledger_master';
    public $timestamps = false;

    protected $fillable = [
        'gl_code',
        'gl_name',
        'category_id',
        'proportion',
        'depreciation',
    ];

    protected $casts = [
        'proportion' => 'decimal:2',
        'depreciation' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategoryMaster::class, 'category_id');
    }
}
