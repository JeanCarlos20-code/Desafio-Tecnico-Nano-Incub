<?php

namespace Tests\Feature\Database;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use App\Modules\User\Infra\Database\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_database_already_persists_the_demo_rooms_and_reservations_without_seed(): void
    {
        $this->assertDatabaseCount('rooms', 3);
        $this->assertDatabaseCount('reservations', 3);
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
    }

    public function test_database_seeder_does_not_duplicate_migrated_demo_catalog(): void
    {
        $this->assertDatabaseCount('rooms', 3);
        $this->assertDatabaseCount('reservations', 3);

        $this->seed();

        $this->assertDatabaseCount('rooms', 3);
        $this->assertDatabaseCount('reservations', 3);
        $this->assertSame(1, Room::query()->where('name', 'Sala Reunião Norte')->count());
        $this->assertSame(1, Reservation::query()->where('title', 'Reunião da manhã')->count());
        $this->assertSame(1, Reservation::query()->where('title', 'Alinhamento seguinte')->count());
        $this->assertSame(1, Reservation::query()->where('title', 'Treinamento da tarde')->count());
    }

    public function test_database_seeder_keeps_exactly_the_three_migrated_administrators(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseHas('users', ['name' => 'Gertrudes', 'email' => 'teste@mail.com']);
        $this->assertDatabaseHas('users', ['name' => 'Marcelo', 'email' => 'teste2@mail.com']);
        $this->assertDatabaseHas('users', ['name' => 'Emerson', 'email' => 'teste3@mail.com']);
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_database_seeder_creates_the_three_known_administrators_when_those_emails_are_missing(): void
    {
        User::query()->whereIn('email', [
            'teste@mail.com',
            'teste2@mail.com',
            'teste3@mail.com',
        ])->forceDelete();

        $this->assertDatabaseCount('users', 0);

        $this->seed();

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseHas('users', ['name' => 'Gertrudes', 'email' => 'teste@mail.com']);
        $this->assertDatabaseHas('users', ['name' => 'Marcelo', 'email' => 'teste2@mail.com']);
        $this->assertDatabaseHas('users', ['name' => 'Emerson', 'email' => 'teste3@mail.com']);
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
        $this->assertDatabaseCount('rooms', 3);
        $this->assertDatabaseCount('reservations', 3);

        foreach (['teste@mail.com', 'teste2@mail.com', 'teste3@mail.com'] as $email) {
            $hash = DB::table('users')->where('email', $email)->value('password');

            $this->assertIsString($hash);
            $this->assertNotSame('Senha123', $hash);
            $this->assertTrue(Hash::check('Senha123', $hash));
            $this->assertSame('argon2i', password_get_info($hash)['algoName']);
        }
    }

    public function test_database_seeder_does_not_update_passwords_when_the_three_emails_already_exist(): void
    {
        $original = DB::table('users')->where('email', 'teste@mail.com')->value('password');

        $this->seed();

        $this->assertSame($original, DB::table('users')->where('email', 'teste@mail.com')->value('password'));
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_database_seeder_persists_the_three_named_active_rooms(): void
    {
        $this->seed();

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
    }

    public function test_database_seeder_persists_three_active_reservations_that_satisfy_rf13_to_rf18(): void
    {
        $this->seed();

        $norte = Room::query()->where('name', 'Sala Reunião Norte')->firstOrFail();
        $treinamento = Room::query()->where('name', 'Sala Treinamento')->firstOrFail();
        $diretoria = Room::query()->where('name', 'Sala Diretoria')->firstOrFail();
        $day = now()->addDay()->toDateString();

        $this->assertDatabaseCount('reservations', 3);
        $this->assertSame(0, Reservation::query()->where('room_id', $diretoria->id)->count());

        $norteMorning = Reservation::query()
            ->where('room_id', $norte->id)
            ->where('starts_at', $day.' 09:00:00')
            ->where('ends_at', $day.' 10:00:00')
            ->where('participants', 4)
            ->whereNull('cancelled_at')
            ->firstOrFail();

        $norteNext = Reservation::query()
            ->where('room_id', $norte->id)
            ->where('starts_at', $day.' 10:00:00')
            ->where('ends_at', $day.' 11:00:00')
            ->where('participants', 6)
            ->whereNull('cancelled_at')
            ->firstOrFail();

        $treinamentoAfternoon = Reservation::query()
            ->where('room_id', $treinamento->id)
            ->where('starts_at', $day.' 14:00:00')
            ->where('ends_at', $day.' 16:00:00')
            ->where('participants', 12)
            ->whereNull('cancelled_at')
            ->firstOrFail();

        $this->assertTrue($norteMorning->ends_at->equalTo($norteNext->starts_at));

        $reservations = [$norteMorning, $norteNext, $treinamentoAfternoon];

        foreach ($reservations as $reservation) {
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
}
