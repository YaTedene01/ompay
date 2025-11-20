<?php

namespace App\Services;

use App\Models\User;
use App\Repository\UserRepository;
use Illuminate\Support\Str;

class UserService
{
    public UserRepository $repo;

    public function __construct(UserRepository $repo)
    {
        $this->repo = $repo;
    }

    public function createUserForPhone(string $phone): User
    {
        $existing = $this->repo->findByPhone($phone);
        if ($existing) {
            return $existing;
        }

        return $this->repo->create([
            'phone' => $phone,
            'name' => 'User ' . $phone,
            'email' => Str::lower(Str::slug($phone)) . '@example.local',
            'password' => bcrypt(Str::random(12)),
        ]);
    }

    public function verifyPhone(User $user): bool
    {
        return $this->repo->update($user, [
            'is_phone_verified' => true,
            'phone_verified_at' => now(),
        ]);
    }
}