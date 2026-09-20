<?php

namespace Database\Seeders;

use App\Modules\Room\Infra\Database\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        Room::query()->firstOrCreate(
            ['name' => 'Sala Reunião Norte'],
            ['capacity' => 8, 'is_active' => true],
        );

        Room::query()->firstOrCreate(
            ['name' => 'Sala Treinamento'],
            ['capacity' => 20, 'is_active' => true],
        );

        Room::query()->firstOrCreate(
            ['name' => 'Sala Diretoria'],
            ['capacity' => 4, 'is_active' => true],
        );
    }
}
