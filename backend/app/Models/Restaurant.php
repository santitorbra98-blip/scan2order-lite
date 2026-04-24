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
        'active'   => 'boolean',
        'schedule' => 'array',
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

    public function admins()
    {
        return $this->belongsToMany(User::class, 'user_restaurant')
            ->withPivot('role_id')
            ->whereHas('role', function ($query) {
                $query->where('name', 'admin');
            });
    }
}
