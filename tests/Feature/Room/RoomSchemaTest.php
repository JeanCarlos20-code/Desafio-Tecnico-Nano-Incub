<?php

namespace Tests\Feature\Room;

use App\Modules\Room\Infra\Database\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoomSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_persists_room_matching_rooms_table_columns(): void
    {
        $room = Room::factory()->create([
            'name' => 'Sala Azul',
            'capacity' => 12,
            'is_active' => true,
        ]);

        $room->refresh();

        $this->assertTrue(Str::isUuid($room->id));
        $this->assertSame('7', $room->id[14]);
        $this->assertSame('Sala Azul', $room->name);
        $this->assertSame(12, $room->capacity);
        $this->assertTrue($room->is_active);
        $this->assertNotNull($room->created_at);
        $this->assertNotNull($room->updated_at);

        $allowed = ['id', 'name', 'capacity', 'is_active', 'created_at', 'updated_at', 'deleted_at'];
        $this->assertSame([], array_values(array_diff(array_keys($room->getAttributes()), $allowed)));

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Sala Azul',
            'capacity' => 12,
            'is_active' => 1,
        ]);
    }

    public function test_soft_delete_sets_deleted_at_and_hides_the_row_from_default_queries(): void
    {
        $room = Room::factory()->create([
            'name' => 'Sala Verde',
        ]);

        $room->delete();

        $this->assertNotNull($room->deleted_at);
        $this->assertNull(Room::query()->find($room->id));
        $this->assertNotNull(Room::withTrashed()->find($room->id));
    }
}
