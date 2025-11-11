<?php

namespace App\Utils;

use Illuminate\Support\Str;

trait GenererUuid
{
    public static function bootGenererUuid(): void
    {
        static::creating(function ($model) {
            $key = $model->getKeyName();
            if (empty($model->{$key})) {
                $model->{$key} = (string) Str::uuid();
            }
        });
    }
}
