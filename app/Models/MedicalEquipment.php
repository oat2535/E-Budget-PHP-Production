<?php

namespace App\Models;

use App\Models\Concerns\HasMonthlyDetails;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalEquipment extends Model
{
    use HasMonthlyDetails;

    protected $connection = 'pgsql';
    protected $table = 'ebudget_medical_equipment';

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
        'item_name',
        'description',
        'purchase_price',
        'base_branch_id',
        'department_id',
        'cost_center_name',
        'document_no',
        'item_master_id',
        'general_ledger_code',
        'budget_year',
        'status',
        'cancel_reason',
        'create_eid',
        'modify_eid',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
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
        return $this->hasMany(BudgetMonthlyDetail::class, 'med_equip_id');
    }
}
