<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Utils\GenererUuid;

class Transaction extends Model
{
    use HasFactory, GenererUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    // Transaction API uses 'montant' (French)
    protected $hidden = [];

    protected $fillable = ['id', 'compte_id', 'type', 'montant', 'status', 'counterparty', 'metadata', 'created_by'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function compte()
    {
        return $this->belongsTo(Compte::class, 'compte_id');
    }

    public function getMontantAttribute($value)
    {
        return $value ?? 0;
    }

    public function setMontantAttribute($value)
    {
        $this->attributes['montant'] = $value;
    }
}
