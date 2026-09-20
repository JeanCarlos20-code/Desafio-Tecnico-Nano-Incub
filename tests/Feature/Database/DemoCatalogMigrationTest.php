<?php

namespace Tests\Feature\Database;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoCatalogMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_database_persists_the_three_named_active_demo_rooms(): void
    {
        $this->assertDatabaseCount('rooms', 3);
        $this->assertDatabaseHas('rooms', [
            'name' => 'Sala Reunião Norte',
            'capacity' => 8,
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('rooms', [
            'name' => 'Sala Treinamento',
            'capacity' => 20,
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('rooms', [
            'name' => 'Sala Diretoria',
            'capacity' => 4,
            'is_active' => 1,
        ]);

        foreach (Room::query()->get() as $room) {
            $this->assertFalse(Str::isUuid((string) $room->id));
            $this->assertNotFalse(filter_var($room->id, FILTER_VALIDATE_INT));
            $this->assertGreaterThan(0, (int) $room->id);
            $this->assertTrue($room->is_active);
            $this->assertNull($room->deleted_at);
            $this->assertNotNull($room->created_at);
            $this->assertNotNull($room->updated_at);
        }
    }

    public function test_refresh_database_persists_three_active_demo_reservations_that_satisfy_rf13_to_rf18(): void
    {
        $norte = Room::query()->where('name', 'Sala Reunião Norte')->firstOrFail();
        $treinamento = Room::query()->where('name', 'Sala Treinamento')->firstOrFail();
        $diretoria = Room::query()->where('name', 'Sala Diretoria')->firstOrFail();
        $day = now()->addDay()->toDateString();

        $this->assertDatabaseCount('reservations', 3);
        $this->assertSame(0, Reservation::query()->where('room_id', $diretoria->id)->count());

        $norteMorning = Reservation::query()
            ->where('room_id', $norte->id)
            ->where('title', 'Reunião da manhã')
            ->where('responsible', 'Gertrudes')
            ->where('starts_at', $day.' 09:00:00')
            ->where('ends_at', $day.' 10:00:00')
            ->where('participants', 4)
            ->whereNull('cancelled_at')
            ->firstOrFail();

        $norteNext = Reservation::query()
            ->where('room_id', $norte->id)
            ->where('title', 'Alinhamento seguinte')
            ->where('responsible', 'Marcelo')
            ->where('starts_at', $day.' 10:00:00')
            ->where('ends_at', $day.' 11:00:00')
            ->where('participants', 6)
            ->whereNull('cancelled_at')
            ->firstOrFail();

        $treinamentoAfternoon = Reservation::query()
            ->where('room_id', $treinamento->id)
            ->where('title', 'Treinamento da tarde')
            ->where('responsible', 'Emerson')
            ->where('starts_at', $day.' 14:00:00')
            ->where('ends_at', $day.' 16:00:00')
            ->where('participants', 12)
            ->whereNull('cancelled_at')
            ->firstOrFail();

        $this->assertTrue($norteMorning->ends_at->equalTo($norteNext->starts_at));

        $reservations = [$norteMorning, $norteNext, $treinamentoAfternoon];

        foreach ($reservations as $reservation) {
            $this->assertFalse(Str::isUuid((string) $reservation->id));
            $this->assertNotFalse(filter_var($reservation->id, FILTER_VALIDATE_INT));
            $this->assertGreaterThan(0, (int) $reservation->id);
            $this->assertNotFalse(filter_var($reservation->room_id, FILTER_VALIDATE_INT));
            $this->assertNotNull($reservation->created_at);
            $this->assertNotNull($reservation->updated_at);

            $room = $reservation->room()->firstOrFail();
            $durationMinutes = $reservation->starts_at->diffInMinutes($reservation->ends_at);

            $this->assertTrue($reservation->ends_at->greaterThan($reservation->starts_at));
            $this->assertGreaterThanOrEqual(30, $durationMinutes);
            $this->assertLessThanOrEqual(240, $durationMinutes);
            $this->assertFalse($reservation->starts_at->isPast());
            $this->assertLessThanOrEqual($room->capacity, $reservation->participants);
            $this->assertTrue($room->is_active);
            $this->assertNull($reservation->cancelled_at);
        }

        foreach ($reservations as $index => $reservation) {
            foreach ($reservations as $otherIndex => $other) {
                if ($index >= $otherIndex || $reservation->room_id !== $other->room_id) {
                    continue;
                }

                $overlaps = $reservation->starts_at->lt($other->ends_at)
                    && $reservation->ends_at->gt($other->starts_at);

                $this->assertFalse($overlaps);
            }
        }
    }

    public function test_rolling_back_the_demo_catalog_migration_deletes_only_those_rows(): void
    {
        $extraRoom = Room::factory()->create([
            'name' => 'Sala Extra',
            'capacity' => 5,
        ]);
        Reservation::factory()->create([
            'room_id' => $extraRoom->id,
            'title' => 'Reserva Extra',
            'responsible' => 'Ada Lovelace',
        ]);

        $this->assertDatabaseCount('rooms', 4);
        $this->assertDatabaseCount('reservations', 4);

        $this->artisan('migrate:rollback', [
            '--path' => 'database/migrations/2026_09_20_000000_insert_demo_rooms_and_reservations.php',
        ])->assertSuccessful();

        $this->assertDatabaseMissing('rooms', ['name' => 'Sala Reunião Norte']);
        $this->assertDatabaseMissing('rooms', ['name' => 'Sala Treinamento']);
        $this->assertDatabaseMissing('rooms', ['name' => 'Sala Diretoria']);
        $this->assertDatabaseMissing('reservations', ['title' => 'Reunião da manhã']);
        $this->assertDatabaseMissing('reservations', ['title' => 'Alinhamento seguinte']);
        $this->assertDatabaseMissing('reservations', ['title' => 'Treinamento da tarde']);
        $this->assertDatabaseHas('rooms', ['name' => 'Sala Extra']);
        $this->assertDatabaseHas('reservations', ['title' => 'Reserva Extra']);
        $this->assertDatabaseCount('rooms', 1);
        $this->assertDatabaseCount('reservations', 1);
    }
}
