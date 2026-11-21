<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlActualTemplate extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'ebudget_gl_actual_template';

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
        'template_row_id',
        'row_order',
        'code',
        'label',
        'row_type',
        'actual_value',
        'per_month_value',
        'annualized_value',
        'sales_ratio',
        'growth_rate',
        'department_id',
        'base_branch_id',
        'budget_year',
        'actual_month_count',
        'growth_pct_1',
        'growth_pct_2',
        'growth_pct_3',
        'document_no',
        'status',
        'cancel_reason',
        'create_eid',
        'modify_eid',
    ];

    protected $casts = [
        'row_order' => 'integer',
        'budget_year' => 'integer',
        'actual_month_count' => 'integer',
        'actual_value' => 'decimal:2',
        'per_month_value' => 'decimal:2',
        'annualized_value' => 'decimal:2',
        'sales_ratio' => 'decimal:2',
        'growth_rate' => 'decimal:2',
        'growth_pct_1' => 'decimal:2',
        'growth_pct_2' => 'decimal:2',
        'growth_pct_3' => 'decimal:2',
        'create_date' => 'datetime:Y-m-d H:i:s',
        'modify_date' => 'datetime:Y-m-d H:i:s',
    ];

    public function templateRow(): BelongsTo
    {
        return $this->belongsTo(GlActualTemplateRow::class, 'template_row_id');
    }
}
