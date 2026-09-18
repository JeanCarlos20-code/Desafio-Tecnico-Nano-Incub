<?php

namespace Database\Factories;

use App\Modules\Room\Infra\Database\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'capacity' => fake()->numberBetween(1, 40),
            'is_active' => true,
        ];
    }
}
