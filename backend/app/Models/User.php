<?php

namespace App\Models;

use App\Traits\HasManagedRestaurants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasManagedRestaurants, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'terms_accepted_at',
        'privacy_accepted_at',
        'marketing_consent_at',
        'legal_version',
        'legal_acceptance_ip',
        'legal_acceptance_user_agent',
        'role_id',
        'created_by',
        'status',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'terms_accepted_at' => 'datetime',
        'privacy_accepted_at' => 'datetime',
        'marketing_consent_at' => 'datetime',        'max_restaurants'     => 'integer',
        'max_catalogs'        => 'integer',
        'max_products'        => 'integer',    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function restaurants()
    {
        return $this->belongsToMany(Restaurant::class, 'user_restaurant')
                    ->withPivot('role_id');
    }

    public function hasRole($roleName)
    {
        if (!$this->role) {
            return false;
        }
        return $this->role->name === $roleName;
    }

    public function hasAnyRole($roles)
    {
        $roles = is_array($roles) ? $roles : func_get_args();
        if (!$this->role) {
            return false;
        }
        return in_array($this->role->name, $roles);
    }

    public function permissions()
    {
        return $this->role ? $this->role->permissions : collect();
    }

    public function hasPermission($permissionName)
    {
        return $this->permissions()->contains('name', $permissionName);
    }
}
