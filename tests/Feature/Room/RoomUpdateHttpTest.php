<?php

namespace Tests\Feature\Room;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoomUpdateHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(now()->timezone((string) config('app.timezone'))->setDate(2026, 9, 21)->setTime(12, 0));
    }

    public function test_edit_page_renders_inertia_room_edit_with_future_active_count(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create([
            'name' => 'Sala Azul',
            'capacity' => 10,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->get(route('rooms.edit', $room))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Room/Edit')
                ->where('room.id', (string) $room->id)
                ->where('room.name', 'Sala Azul')
                ->where('room.capacity', 10)
                ->where('room.is_active', false)
                ->where('future_active_count', 0)
                ->missing('has_registered_meetings')
            );
    }

    public function test_update_persists_name_capacity_and_is_active_and_redirects_with_flash(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create([
            'name' => 'Sala Azul',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('rooms.edit', $room))
            ->put(route('rooms.update', $room), [
                'name' => 'Sala Verde',
                'capacity' => 20,
                'is_active' => false,
            ])
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHas('success', 'Sala atualizada com sucesso.');

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Sala Verde',
            'capacity' => 20,
            'is_active' => 0,
        ]);
    }

    public function test_put_deactivate_with_keep_persists_inactive_room_keeps_future_actives_and_flashes_keep(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create(['is_active' => true]);
        $future = Reservation::factory()->create([
            'room_id' => $room->id,
            'starts_at' => '2026-09-22 10:00:00',
            'ends_at' => '2026-09-22 10:30:00',
        ]);

        $this->actingAs($user)
            ->from(route('rooms.edit', $room))
            ->put(route('rooms.update', $room), [
                'name' => $room->name,
                'capacity' => $room->capacity,
                'is_active' => false,
                'scheduled_meetings_action' => 'keep',
            ])
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHas('success', 'Sala desativada. As reuniões programadas foram mantidas.');

        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'is_active' => 0]);
        $this->assertDatabaseHas('reservations', ['id' => $future->id, 'cancelled_at' => null]);
    }

    public function test_put_deactivate_with_cancel_cancels_only_future_actives_and_flashes_cancel(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create(['is_active' => true]);
        $future = Reservation::factory()->create([
            'room_id' => $room->id,
            'starts_at' => '2026-09-22 10:00:00',
            'ends_at' => '2026-09-22 10:30:00',
        ]);
        $inProgress = Reservation::factory()->create([
            'room_id' => $room->id,
            'starts_at' => '2026-09-21 11:00:00',
            'ends_at' => '2026-09-21 13:00:00',
        ]);
        $already = Reservation::factory()->create([
            'room_id' => $room->id,
            'starts_at' => '2026-09-23 10:00:00',
            'ends_at' => '2026-09-23 10:30:00',
            'cancelled_at' => '2026-09-20 09:00:00',
        ]);

        $this->actingAs($user)
            ->from(route('rooms.edit', $room))
            ->put(route('rooms.update', $room), [
                'name' => $room->name,
                'capacity' => $room->capacity,
                'is_active' => false,
                'scheduled_meetings_action' => 'cancel',
            ])
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHas('success', 'Sala desativada. As reuniões futuras foram canceladas.');

        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'is_active' => 0]);
        $this->assertNotNull($future->fresh()->cancelled_at);
        $this->assertNull($inProgress->fresh()->cancelled_at);
        $this->assertSame('2026-09-20 09:00:00', $already->fresh()->cancelled_at->format('Y-m-d H:i:s'));
    }

    public function test_put_deactivate_without_action_returns_422_future_active_count_and_writes_nothing(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create([
            'name' => 'Sala Azul',
            'capacity' => 10,
            'is_active' => true,
        ]);
        $future = Reservation::factory()->create([
            'room_id' => $room->id,
            'starts_at' => '2026-09-22 10:00:00',
            'ends_at' => '2026-09-22 10:30:00',
        ]);

        $this->actingAs($user)
            ->from(route('rooms.edit', $room))
            ->withHeaders(['Accept' => 'application/json'])
            ->put(route('rooms.update', $room), [
                'name' => 'Sala Verde',
                'capacity' => 20,
                'is_active' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.future_active_count.0', '1')
            ->assertJsonValidationErrors(['scheduled_meetings_action']);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Sala Azul',
            'capacity' => 10,
            'is_active' => 1,
        ]);
        $this->assertNull($future->fresh()->cancelled_at);
    }

    public function test_update_validation_failure_leaves_persisted_data_unchanged(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create([
            'name' => 'Sala Azul',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('rooms.edit', $room))
            ->put(route('rooms.update', $room), [
                'name' => '',
                'capacity' => 0,
            ])
            ->assertRedirect(route('rooms.edit', $room))
            ->assertSessionHasErrors(['name', 'capacity', 'is_active']);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Sala Azul',
            'capacity' => 10,
            'is_active' => 1,
        ]);
    }
}
