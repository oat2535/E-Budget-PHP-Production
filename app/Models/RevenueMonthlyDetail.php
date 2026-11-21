<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueMonthlyDetail extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'ebudget_revenue_monthly_detail';
    public $timestamps = false;

    protected $fillable = [
        'month',
        'detail_note',
        'amount',
        'cumulative_amount',
        'revenue_entry_id',
    ];

    protected $casts = [
        'month' => 'integer',
        'amount' => 'decimal:2',
        'cumulative_amount' => 'decimal:2',
    ];

    public function revenueEntry(): BelongsTo
    {
        return $this->belongsTo(Revenue::class, 'revenue_entry_id');
    }
}
