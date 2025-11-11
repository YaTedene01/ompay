<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition()
    {
        return [
            'compte_id' => null,
            'type' => 'depot',
            'montant' => $this->faker->randomFloat(2, 1, 1000),
            'status' => 'completed',
            'counterparty' => null,
            'metadata' => null,
            'created_by' => null,
        ];
    }
}
