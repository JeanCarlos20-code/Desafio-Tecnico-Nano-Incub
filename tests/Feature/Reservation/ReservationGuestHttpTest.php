<?php

namespace Tests\Feature\Reservation;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservationGuestHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    #[DataProvider('guestReservationRoutes')]
    public function test_guest_reservation_routes_redirect_to_login_without_writing_rows(string $method, string $path): void
    {
        $room = Room::factory()->create();
        Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Existing',
        ]);

        $this->{$method}($path, [
            'room_id' => $room->id,
            'responsible' => 'Attacker',
            'title' => 'Attack',
            'starts_at' => '2026-09-21 10:00:00',
            'ends_at' => '2026-09-21 10:30:00',
            'participants' => 2,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('reservations', 4);
        $this->assertDatabaseHas('reservations', ['title' => 'Existing']);
        $this->assertDatabaseMissing('reservations', ['title' => 'Attack']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guestReservationRoutes(): array
    {
        $id = '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11';

        return [
            'GET /reservations' => ['get', '/reservations'],
            'GET /reservations/create' => ['get', '/reservations/create'],
            'POST /reservations' => ['post', '/reservations'],
            'PATCH /reservations/{id}/cancel' => ['patch', "/reservations/{$id}/cancel"],
        ];
    }
}
