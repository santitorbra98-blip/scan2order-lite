<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    public const DEFAULTS = [
        'superadmin' => ['is_global' => true],
        'admin' => ['is_global' => true],
    ];

    protected $fillable = ['name', 'is_global'];

    public static function ensureDefault(string $name): self
    {
        return static::firstOrCreate(
            ['name' => $name],
            static::DEFAULTS[$name] ?? ['is_global' => false],
        );
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }
}
