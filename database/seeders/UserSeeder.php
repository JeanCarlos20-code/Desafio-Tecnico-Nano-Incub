<?php

namespace Database\Seeders;

use App\Modules\User\Infra\Database\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'teste@mail.com'],
            ['name' => 'Gertrudes', 'password' => 'Senha123'],
        );

        User::query()->firstOrCreate(
            ['email' => 'teste2@mail.com'],
            ['name' => 'Marcelo', 'password' => 'Senha123'],
        );

        User::query()->firstOrCreate(
            ['email' => 'teste3@mail.com'],
            ['name' => 'Emerson', 'password' => 'Senha123'],
        );
    }
}
