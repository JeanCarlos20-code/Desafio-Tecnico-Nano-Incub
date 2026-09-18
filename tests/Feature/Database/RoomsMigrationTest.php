<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoomsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_database_applies_adr_004_rooms_columns_from_migrations(): void
    {
        $expected = ['capacity', 'created_at', 'deleted_at', 'id', 'is_active', 'name', 'updated_at'];
        $actual = Schema::getColumnListing('rooms');
        sort($actual);

        $this->assertSame($expected, $actual);

        foreach ($expected as $column) {
            $this->assertTrue(Schema::hasColumn('rooms', $column), "Missing column: {$column}");
        }

        $this->assertFalse(Schema::hasColumn('rooms', 'location'));
    }
}
