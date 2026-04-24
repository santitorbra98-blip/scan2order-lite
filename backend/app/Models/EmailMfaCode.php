<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailMfaCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'purpose',
        'code_hash',
        'payload',
        'expires_at',
        'used_at',
        'attempts',
    ];

    protected $casts = [
        'payload'    => 'array',
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];
}
