<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Utils\GenererUuid;

class Compte extends Model
{
    use HasFactory, GenererUuid;

    // New table name after migration
    protected $table = 'comptes';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'user_id', 'solde', 'devise'];

    // API uses French names (solde, devise)
    protected $hidden = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getSoldeAttribute($value)
    {
        return $value ?? 0;
    }

    public function setSoldeAttribute($value)
    {
        $this->attributes['solde'] = $value;
    }

    public function getDeviseAttribute($value)
    {
        return $value ?? 'XOF';
    }

    public function setDeviseAttribute($value)
    {
        $this->attributes['devise'] = $value;
    }
}
