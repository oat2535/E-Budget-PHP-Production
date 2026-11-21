<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlActualTemplateRow extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'master_data_ebudget_gl_actual_template_row';
    public $timestamps = false;

    protected $fillable = [
        'row_order',
        'code',
        'label',
        'row_type',
    ];

    protected $casts = [
        'row_order' => 'integer',
    ];
}
