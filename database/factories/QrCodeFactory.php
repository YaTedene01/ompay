<?php

namespace Database\Factories;

use App\Models\QrCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class QrCodeFactory extends Factory
{
    protected $model = QrCode::class;

    public function definition()
    {
        return [
            'user_id' => null,
            'code' => Str::random(40),
            'meta' => null,
        ];
    }
}
