<?php

namespace Tests\Feature\Room;

use App\Modules\Room\Infra\Database\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        $this->assertFalse(Str::isUuid((string) $room->id));
        $this->assertNotFalse(filter_var($room->id, FILTER_VALIDATE_INT));
        $this->assertGreaterThan(0, (int) $room->id);
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

    public function test_rooms_is_active_mysql_column_default_is_boolean_true(): void
    {
        $column = collect(Schema::getColumns('rooms'))->firstWhere('name', 'is_active');

        $this->assertNotNull($column);
        $this->assertTrue(in_array($column['default'], [true, 1, '1'], true), 'Expected is_active default true');

        DB::table('rooms')->insert([
            'name' => 'Sala Default',
            'capacity' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue((bool) DB::table('rooms')->where('name', 'Sala Default')->value('is_active'));
    }
}
