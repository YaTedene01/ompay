<?php

namespace App\Repository;

use App\Models\Compte;
use App\Models\User;

class CompteRepository
{
    private Compte $compte;

    public function __construct($compte)
    {
        $this->compte = $compte;
    }

    public function create(array $data): Compte
    {
        return $this->compte->create($data);
    }

    public function getByUser(User $user): ?Compte
    {
        return Compte::where('user_id', $user->id)->first();
    }

    public function createForUser(User $user, array $data = []): Compte
    {
        return $user->compte()->create(array_merge(['solde' => 0, 'devise' => 'XOF'], $data));
    }

    public function find(string $id): ?Compte
    {
        return Compte::find($id);
    }

    public function updateBalance(Compte $compte, float $newBalance): Compte
    {
        $compte->solde = $newBalance;
        $compte->save();
        return $compte;
    }
}
