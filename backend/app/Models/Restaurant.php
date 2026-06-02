<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Restaurant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'image',
        'active',
        'schedule',
        'created_by',
    ];

    protected $casts = [
        'active'      => 'boolean',
        'schedule'    => 'array',
        'created_by'  => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function catalogs()
    {
        return $this->hasMany(Catalog::class)->orderBy('order');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_restaurant')
                    ->withPivot('role_id');
    }

    /**
     * Users that manage this restaurant with the 'admin' role.
     *
     * Uses a whereIn subquery on role_id so that Eloquent's whereHas() can
     * embed this relationship as a correlated EXISTS without needing the roles
     * table in the outer FROM clause (which broke PostgreSQL).
     */
    public function admins()
    {
        return $this->belongsToMany(User::class, 'user_restaurant')
            ->withPivot('role_id')
            ->whereIn('user_restaurant.role_id', function ($query) {
                $query->select('id')->from('roles')->where('name', 'admin');
            });
    }
}
