<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $connection = 'pgsql';
    protected $table = 'auth_user';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'first_name',
        'last_name',
        'email',
        'password',
        'is_staff',
        'is_superuser',
        'is_active',
        'date_joined',
        'last_login',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_staff' => 'boolean',
        'is_superuser' => 'boolean',
        'is_active' => 'boolean',
        'date_joined' => 'datetime',
        'last_login' => 'datetime',
    ];

    public ?bool $_is_admin_cached = null;
    public ?string $bank_account_note = null;
    public ?string $bank_sub_name = null;

    public function getNameAttribute(): string
    {
        $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return $name ?: ($this->username ?? '');
    }

    public function getIsAuthenticatedAttribute(): bool
    {
        return true;
    }
}
