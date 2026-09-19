<?php

namespace Database\Factories;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDay()->setTime(9, 0);

        return [
            'room_id' => Room::factory(),
            'responsible' => fake()->name(),
            'title' => fake()->sentence(3),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'participants' => fake()->numberBetween(1, 8),
            'cancelled_at' => null,
        ];
    }
}
