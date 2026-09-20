<?php

namespace Database\Seeders;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use Illuminate\Database\Seeder;

class ReservationSeeder extends Seeder
{
    public function run(): void
    {
        $norte = Room::query()->where('name', 'Sala Reunião Norte')->firstOrFail();
        $treinamento = Room::query()->where('name', 'Sala Treinamento')->firstOrFail();
        $day = now()->addDay()->startOfDay();

        Reservation::query()->firstOrCreate(
            [
                'room_id' => $norte->id,
                'title' => 'Reunião da manhã',
            ],
            [
                'responsible' => 'Gertrudes',
                'starts_at' => $day->copy()->setTime(9, 0),
                'ends_at' => $day->copy()->setTime(10, 0),
                'participants' => 4,
                'cancelled_at' => null,
            ],
        );

        Reservation::query()->firstOrCreate(
            [
                'room_id' => $norte->id,
                'title' => 'Alinhamento seguinte',
            ],
            [
                'responsible' => 'Marcelo',
                'starts_at' => $day->copy()->setTime(10, 0),
                'ends_at' => $day->copy()->setTime(11, 0),
                'participants' => 6,
                'cancelled_at' => null,
            ],
        );

        Reservation::query()->firstOrCreate(
            [
                'room_id' => $treinamento->id,
                'title' => 'Treinamento da tarde',
            ],
            [
                'responsible' => 'Emerson',
                'starts_at' => $day->copy()->setTime(14, 0),
                'ends_at' => $day->copy()->setTime(16, 0),
                'participants' => 12,
                'cancelled_at' => null,
            ],
        );
    }
}
