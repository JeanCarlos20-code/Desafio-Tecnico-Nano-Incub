<?php

namespace Tests\Feature\Room;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoomDestroyHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_destroy_soft_deletes_redirects_with_flash_and_omits_the_room_from_later_index(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create([
            'name' => 'Sala Azul',
        ]);
        $kept = Room::factory()->create([
            'name' => 'Sala Verde',
        ]);

        $this->actingAs($user)
            ->from(route('rooms.index'))
            ->delete(route('rooms.destroy', $room))
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHas('success', 'Sala excluída com sucesso.');

        $this->assertSoftDeleted('rooms', ['id' => $room->id]);
        $this->assertNotNull(Room::withTrashed()->find($room->id)?->deleted_at);

        $this->actingAs($user)
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Room/Index')
                ->has('rooms.data', 4)
                ->where('rooms.data', function (Collection $rows) use ($room, $kept): bool {
                    $ids = $rows->pluck('id');

                    return $ids->contains($kept->id) && ! $ids->contains($room->id);
                })
            );
    }

    public function test_destroy_cancels_all_actives_soft_deletes_the_room_and_keeps_reservation_rows(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create(['name' => 'Sala Azul']);
        $future = Reservation::factory()->create([
            'room_id' => $room->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(30),
        ]);
        $already = Reservation::factory()->create([
            'room_id' => $room->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addMinutes(30),
            'cancelled_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->from(route('rooms.index'))
            ->delete(route('rooms.destroy', $room))
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHas('success', 'Sala excluída com sucesso.');

        $this->assertSoftDeleted('rooms', ['id' => $room->id]);
        $this->assertNotNull($future->fresh()->cancelled_at);
        $this->assertNotNull($already->fresh());
        $this->assertSame(
            $already->cancelled_at->format('Y-m-d H:i:s'),
            $already->fresh()->cancelled_at->format('Y-m-d H:i:s'),
        );
        $this->assertDatabaseHas('reservations', ['id' => $future->id]);
        $this->assertDatabaseHas('reservations', ['id' => $already->id]);
    }

    #[DataProvider('missingRoomActions')]
    public function test_unknown_malformed_or_soft_deleted_room_returns_404_and_leaves_other_rows_unchanged(
        string $scenario,
        string $method,
        callable $path,
    ): void {
        $user = UserModel::factory()->create();
        $kept = Room::factory()->create(['name' => 'Sala Preservada', 'capacity' => 7]);
        $trashed = Room::factory()->create(['name' => 'Sala Morta']);
        $trashed->delete();

        $target = match ($scenario) {
            'unknown' => '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c99',
            'malformed' => 'not-a-uuid',
            'soft-deleted' => $trashed->id,
        };

        $this->actingAs($user)
            ->{$method}($path($target), [
                'name' => 'Hacked',
                'capacity' => 1,
                'is_active' => true,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('rooms', [
            'id' => $kept->id,
            'name' => 'Sala Preservada',
            'capacity' => 7,
        ]);
        $this->assertDatabaseMissing('rooms', ['name' => 'Hacked']);
        $this->assertSoftDeleted('rooms', ['id' => $trashed->id]);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: callable}>
     */
    public static function missingRoomActions(): array
    {
        $edit = fn (string $id): string => "/rooms/{$id}/edit";
        $update = fn (string $id): string => "/rooms/{$id}";
        $destroy = fn (string $id): string => "/rooms/{$id}";

        return [
            'GET unknown' => ['unknown', 'get', $edit],
            'GET malformed' => ['malformed', 'get', $edit],
            'GET soft-deleted' => ['soft-deleted', 'get', $edit],
            'PUT unknown' => ['unknown', 'put', $update],
            'PUT malformed' => ['malformed', 'put', $update],
            'PUT soft-deleted' => ['soft-deleted', 'put', $update],
            'DELETE unknown' => ['unknown', 'delete', $destroy],
            'DELETE malformed' => ['malformed', 'delete', $destroy],
            'DELETE already soft-deleted' => ['soft-deleted', 'delete', $destroy],
        ];
    }
}
