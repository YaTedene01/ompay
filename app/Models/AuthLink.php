<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Utils\GenererUuid;

class AuthLink extends Model
{
    use HasFactory, GenererUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'phone', 'token', 'data', 'expires_at', 'used_at'];

    protected $casts = [
        'data' => 'array',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}
