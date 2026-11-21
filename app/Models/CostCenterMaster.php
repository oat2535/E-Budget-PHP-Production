<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostCenterMaster extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'master_data_ebudget_cost_center_master';
    public $timestamps = false;

    protected $fillable = [
        'cost_center_code',
        'cost_center_name',
        'description',
    ];
}
