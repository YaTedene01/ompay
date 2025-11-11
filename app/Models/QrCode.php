<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Utils\GenererUuid;

class QrCode extends Model
{
    use HasFactory, GenererUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'user_id', 'code', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
