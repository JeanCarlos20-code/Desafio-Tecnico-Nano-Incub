<?php

namespace Tests\Feature\Room;

use App\Modules\Room\Infra\Database\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoomGuestHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    #[DataProvider('guestRoomRoutes')]
    public function test_guest_room_routes_redirect_to_login_without_writing_rows(string $method, string $path): void
    {
        Room::factory()->create(['name' => 'Existing']);

        $this->{$method}($path, [
            'name' => 'Attacker Room',
            'capacity' => 99,
            'is_active' => false,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('rooms', 4);
        $this->assertDatabaseHas('rooms', ['name' => 'Existing']);
        $this->assertDatabaseMissing('rooms', ['name' => 'Attacker Room']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guestRoomRoutes(): array
    {
        $id = '1';

        return [
            'GET /rooms' => ['get', '/rooms'],
            'GET /rooms/create' => ['get', '/rooms/create'],
            'POST /rooms' => ['post', '/rooms'],
            'GET /rooms/{room}/edit' => ['get', "/rooms/{$id}/edit"],
            'PUT /rooms/{room}' => ['put', "/rooms/{$id}"],
            'DELETE /rooms/{room}' => ['delete', "/rooms/{$id}"],
        ];
    }
}
