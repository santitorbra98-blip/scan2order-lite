<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = [
        'catalog_id',
        'name',
        'description',
        'active',
        'order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'order' => 'integer',
    ];

    public function catalog()
    {
        return $this->belongsTo(Catalog::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class)->orderBy('name');
    }
}
