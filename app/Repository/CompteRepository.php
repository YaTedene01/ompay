<?php

namespace App\Repository;

use App\Models\Compte;
use App\Models\User;

class CompteRepository
{
    public function create(array $data): Compte
    {
        return Compte::create($data);
    }

    public function getByUser(User $user): ?Compte
    {
        return $user->compte;
    }

    public function createForUser(User $user, array $data = []): Compte
    {
        return $user->compte()->create(array_merge(['solde' => 0, 'devise' => 'XOF'], $data));
    }

    public function find(string $id): ?Compte
    {
        return Compte::find($id);
    }

    public function updateBalance(Compte $compte, float $newBalance): bool
    {
        return $compte->update(['solde' => $newBalance]);
    }
}
