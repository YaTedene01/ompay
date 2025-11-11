<?php

namespace Database\Factories;

use App\Models\Compte;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompteFactory extends Factory
{
    protected $model = Compte::class;

    public function definition()
    {
        return [
            'user_id' => null,
            'solde' => $this->faker->randomFloat(2, 0, 10000),
            'devise' => 'XOF',
        ];
    }
}
