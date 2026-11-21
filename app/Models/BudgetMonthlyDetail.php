<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetMonthlyDetail extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'ebudget_monthly_detail';
    public $timestamps = false;

    protected $fillable = [
        'vet_budget_id',
        'non_vet_budget_id',
        'position_adj_id',
        'med_equip_id',
        'comp_equip_id',
        'furniture_equip_id',
        'tools_equip_id',
        'car_equip_id',
        'renovate_equip_id',
        'software_equip_id',
        'month',
        'headcount',
        'cost',
        'salary',
        'position_allowance',
        'new_salary',
        'new_allowance',
        'cumulative_depreciation',
    ];

    protected $casts = [
        'month' => 'integer',
        'headcount' => 'decimal:2',
        'cost' => 'decimal:2',
        'salary' => 'decimal:2',
        'position_allowance' => 'decimal:2',
        'new_salary' => 'decimal:2',
        'new_allowance' => 'decimal:2',
        'cumulative_depreciation' => 'decimal:2',
    ];

    public function vetManpower(): BelongsTo
    {
        return $this->belongsTo(VetManpower::class, 'vet_budget_id');
    }

    public function nonVetManpower(): BelongsTo
    {
        return $this->belongsTo(NonVetManpower::class, 'non_vet_budget_id');
    }

    public function positionAdjustment(): BelongsTo
    {
        return $this->belongsTo(PositionAdjustment::class, 'position_adj_id');
    }

    public function medicalEquipment(): BelongsTo
    {
        return $this->belongsTo(MedicalEquipment::class, 'med_equip_id');
    }

    public function computerEquipment(): BelongsTo
    {
        return $this->belongsTo(ComputerEquipment::class, 'comp_equip_id');
    }

    public function furniture(): BelongsTo
    {
        return $this->belongsTo(Furniture::class, 'furniture_equip_id');
    }

    public function toolsEquipment(): BelongsTo
    {
        return $this->belongsTo(ToolsEquipment::class, 'tools_equip_id');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'car_equip_id');
    }

    public function renovate(): BelongsTo
    {
        return $this->belongsTo(Renovate::class, 'renovate_equip_id');
    }

    public function software(): BelongsTo
    {
        return $this->belongsTo(Software::class, 'software_equip_id');
    }
}
