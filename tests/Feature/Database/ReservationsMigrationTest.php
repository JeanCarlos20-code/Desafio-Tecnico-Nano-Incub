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
    }
}
