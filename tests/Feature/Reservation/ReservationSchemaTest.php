<?php

namespace Tests\Feature\Reservation;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_persists_reservation_matching_adr_005_columns_as_uuid_v7(): void
    {
        $room = Room::factory()->create([
            'name' => 'Sala Azul',
            'capacity' => 12,
        ]);

        $reservation = Reservation::factory()->create([
            'room_id' => $room->id,
            'responsible' => 'Ada Lovelace',
            'title' => 'Daily',
            'starts_at' => '2026-09-21 09:00:00',
            'ends_at' => '2026-09-21 09:30:00',
            'participants' => 4,
            'cancelled_at' => null,
        ]);

        $reservation->refresh();

        $this->assertTrue(Str::isUuid($reservation->id));
        $this->assertSame('7', $reservation->id[14]);
        $this->assertSame($room->id, $reservation->room_id);
        $this->assertSame('Ada Lovelace', $reservation->responsible);
        $this->assertSame('Daily', $reservation->title);
        $this->assertSame('2026-09-21 09:00:00', $reservation->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-21 09:30:00', $reservation->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame(4, $reservation->participants);
        $this->assertNull($reservation->cancelled_at);
        $this->assertNotNull($reservation->created_at);
        $this->assertNotNull($reservation->updated_at);

        $allowed = [
            'id',
            'room_id',
            'responsible',
            'title',
            'starts_at',
            'ends_at',
            'participants',
            'cancelled_at',
            'created_at',
            'updated_at',
        ];
        $this->assertSame([], array_values(array_diff(array_keys($reservation->getAttributes()), $allowed)));

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'room_id' => $room->id,
            'responsible' => 'Ada Lovelace',
            'title' => 'Daily',
            'participants' => 4,
        ]);
    }

    public function test_reservations_table_has_no_deleted_at_column(): void
    {
        $this->assertFalse(Schema::hasColumn('reservations', 'deleted_at'));
    }

    public function test_room_id_foreign_key_rejects_unknown_rooms(): void
    {
        $this->expectException(QueryException::class);

        Reservation::factory()->create([
            'room_id' => '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c99',
        ]);
    }
}
