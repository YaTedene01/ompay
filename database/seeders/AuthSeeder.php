<?php

namespace Database\Seeders;

use App\Models\AuthLink;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        AuthLink::create([
            'phone' => '+221700000000',
            'token' => Str::random(48),
            'data' => ['redirect' => config('app.url')],
            'expires_at' => Carbon::now()->addDays(1),
        ]);
    }
}
