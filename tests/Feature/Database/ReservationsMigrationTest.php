<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReservationsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_database_applies_adr_005_reservations_columns_from_migrations(): void
    {
        $expected = [
            'cancelled_at',
            'created_at',
            'ends_at',
            'id',
            'participants',
            'responsible',
            'room_id',
            'starts_at',
            'title',
            'updated_at',
        ];
        $actual = Schema::getColumnListing('reservations');
        sort($actual);

        $this->assertSame($expected, $actual);

        foreach ($expected as $column) {
            $this->assertTrue(Schema::hasColumn('reservations', $column), "Missing column: {$column}");
        }

        $this->assertFalse(Schema::hasColumn('reservations', 'deleted_at'));

        $id = collect(Schema::getColumns('reservations'))->firstWhere('name', 'id');
        $this->assertNotNull($id);
        $this->assertSame('bigint', $id['type_name']);
        $this->assertTrue($id['auto_increment']);

        $roomId = collect(Schema::getColumns('reservations'))->firstWhere('name', 'room_id');
        $this->assertNotNull($roomId);
        $this->assertSame('bigint', $roomId['type_name']);
        $this->assertFalse($roomId['auto_increment']);

        $foreign = collect(Schema::getForeignKeys('reservations'))->first(
            fn (array $key): bool => in_array('room_id', $key['columns'], true),
        );
        $this->assertNotNull($foreign);
        $this->assertSame('rooms', $foreign['foreign_table']);
        $this->assertSame(['id'], $foreign['foreign_columns']);
    }
}
