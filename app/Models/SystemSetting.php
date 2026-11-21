<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'ebudget_system_settings';
    public $timestamps = false;

    protected $fillable = [
        'is_frozen',
        'frozen_message',
        'active_budget_year',
        'frozen_eid',
        'frozen_date',
        'unfrozen_eid',
        'unfrozen_date',
    ];

    protected $casts = [
        'is_frozen' => 'integer',
        'active_budget_year' => 'integer',
        'frozen_date' => 'datetime:Y-m-d H:i:s',
        'unfrozen_date' => 'datetime:Y-m-d H:i:s',
    ];

    public const DEFAULT_FROZEN_MESSAGE = 'ระบบอยู่ระหว่างปิดงบประมาณชั่วคราว ไม่สามารถเพิ่มหรือแก้ไขข้อมูลได้ในขณะนี้ หากมีข้อสงสัยกรุณาติดต่อฝ่ายบัญชี';

    public static function loadSetting(bool $forUpdate = false): self
    {
        $query = $forUpdate ? static::lockForUpdate() : static::query();
        $setting = $query->orderByDesc('id')->first();

        if (! $setting) {
            $setting = static::create([
                'is_frozen' => 0,
                'frozen_message' => self::DEFAULT_FROZEN_MESSAGE,
                'active_budget_year' => (int) date('Y'),
            ]);
        }

        return $setting;
    }

    public static function current(bool $forUpdate = false): self
    {
        return self::loadSetting($forUpdate);
    }
}
