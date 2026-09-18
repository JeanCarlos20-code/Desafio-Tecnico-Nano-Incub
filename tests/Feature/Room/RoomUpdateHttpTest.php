<?php

namespace Tests\Feature\Room;

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
    }

    public function test_edit_page_renders_inertia_room_edit_with_the_room_fields(): void
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
                ->where('room.id', $room->id)
                ->where('room.name', 'Sala Azul')
                ->where('room.capacity', 10)
                ->where('room.is_active', false)
                ->where('has_registered_meetings', false)
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
            ->assertSessionHasErrors(['name', 'capacity']);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Sala Azul',
            'capacity' => 10,
            'is_active' => 1,
        ]);
    }

    public function test_update_without_is_active_keeps_the_stored_is_active(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create([
            'name' => 'Sala Azul',
            'capacity' => 10,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->from(route('rooms.edit', $room))
            ->put(route('rooms.update', $room), [
                'name' => 'Sala Verde',
                'capacity' => 20,
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
}
