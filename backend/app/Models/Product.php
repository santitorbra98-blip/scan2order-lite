<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'restaurant_id',
        'section_id',
        'name',
        'description',
        'image',
        'show_image',
        'is_new',
        'allergens',
        'diet_tags',
        'price',
        'active',
    ];

    protected $casts = [
        'price' => 'float',
        'active' => 'boolean',
        'show_image' => 'boolean',
        'is_new' => 'boolean',
        'allergens' => 'array',
        'diet_tags' => 'array',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
