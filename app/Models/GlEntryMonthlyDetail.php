<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlEntryMonthlyDetail extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'ebudget_gl_entry_monthly_detail';
    public $timestamps = false;

    protected $fillable = [
        'month',
        'detail_note',
        'amount',
        'cumulative_amount',
        'gl_entry_id',
    ];

    protected $casts = [
        'month' => 'integer',
        'amount' => 'decimal:2',
        'cumulative_amount' => 'decimal:2',
    ];

    public function glEntry(): BelongsTo
    {
        return $this->belongsTo(GlEntry::class, 'gl_entry_id');
    }
}
