<?php

namespace Tests\Feature\Room;

use App\Modules\Room\Infra\Database\Models\Room;
use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoomStoreHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_create_page_renders_inertia_room_create(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->get(route('rooms.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Room/Create'));
    }

    public function test_store_persists_uuid_v7_adr_004_columns_defaults_is_active_and_redirects_with_flash(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->from(route('rooms.create'))
            ->post(route('rooms.store'), [
                'name' => 'Sala Azul',
                'capacity' => 10,
            ])
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHas('success', 'Sala criada com sucesso.');

        $this->assertDatabaseCount('rooms', 1);

        $room = Room::query()->first();
        $this->assertNotNull($room);
        $this->assertTrue(Str::isUuid($room->id));
        $this->assertSame('7', $room->id[14]);
        $this->assertSame('Sala Azul', $room->name);
        $this->assertSame(10, $room->capacity);
        $this->assertTrue($room->is_active);
        $this->assertNotNull($room->created_at);
        $this->assertNotNull($room->updated_at);
        $this->assertNull($room->deleted_at);

        $allowed = ['id', 'name', 'capacity', 'is_active', 'created_at', 'updated_at', 'deleted_at'];
        $this->assertSame([], array_values(array_diff(array_keys($room->getAttributes()), $allowed)));
    }

    public function test_store_rejects_invalid_name_or_capacity_and_persists_nothing(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->from(route('rooms.create'))
            ->post(route('rooms.store'), [
                'capacity' => 10,
            ])
            ->assertRedirect(route('rooms.create'))
            ->assertSessionHasErrors(['name' => 'Informe o nome da sala.']);

        $this->actingAs($user)
            ->from(route('rooms.create'))
            ->post(route('rooms.store'), [
                'name' => 'Sala Azul',
                'capacity' => 'abc',
            ])
            ->assertRedirect(route('rooms.create'))
            ->assertSessionHasErrors(['capacity' => 'Informe a capacidade da sala.']);

        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_store_ignores_extra_fields_such_as_location(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->from(route('rooms.create'))
            ->post(route('rooms.store'), [
                'name' => 'Sala Azul',
                'capacity' => 10,
                'location' => 'Andar 2',
            ])
            ->assertRedirect(route('rooms.index'));

        $this->assertFalse(Schema::hasColumn('rooms', 'location'));
        $this->assertDatabaseCount('rooms', 1);
        $this->assertDatabaseHas('rooms', [
            'name' => 'Sala Azul',
            'capacity' => 10,
        ]);
    }

    public function test_store_allows_two_rooms_with_the_same_name(): void
    {
        $user = UserModel::factory()->create();
        Room::factory()->create(['name' => 'Sala Azul']);

        $this->actingAs($user)
            ->post(route('rooms.store'), [
                'name' => 'Sala Azul',
                'capacity' => 6,
                'is_active' => true,
            ])
            ->assertRedirect(route('rooms.index'));

        $this->assertSame(2, Room::query()->where('name', 'Sala Azul')->count());
    }
}
