<?php

namespace Tests\Feature\Reservation;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationCancelHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(now()->timezone((string) config('app.timezone'))->setDate(2026, 9, 21)->setTime(8, 0));
    }

    public function test_cancel_sets_cancelled_at_keeps_the_row_and_frees_the_interval(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create(['capacity' => 8]);
        $reservation = Reservation::factory()->create([
            'room_id' => $room->id,
            'starts_at' => '2026-09-21 10:00:00',
            'ends_at' => '2026-09-21 10:30:00',
        ]);

        $this->actingAs($user)
            ->patch(route('reservations.cancel', $reservation))
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('success', 'Reserva cancelada com sucesso. O horário está disponível novamente.');

        $reservation->refresh();
        $this->assertNotNull($reservation->cancelled_at);
        $this->assertDatabaseCount('reservations', 4);

        $this->actingAs($user)
            ->post(route('reservations.store'), [
                'room_id' => $room->id,
                'responsible' => 'Ada Lovelace',
                'title' => 'Reuso',
                'starts_at' => '2026-09-21 10:00:00',
                'ends_at' => '2026-09-21 10:30:00',
                'participants' => 2,
            ])
            ->assertRedirect(route('reservations.index'));

        $this->assertDatabaseCount('reservations', 5);
        $this->assertDatabaseHas('reservations', ['title' => 'Reuso', 'cancelled_at' => null]);
    }

    public function test_repeated_cancel_leaves_cancelled_at_unchanged(): void
    {
        $user = UserModel::factory()->create();
        $reservation = Reservation::factory()->create([
            'cancelled_at' => '2026-09-21 09:15:00',
        ]);
        $original = $reservation->cancelled_at?->format('Y-m-d H:i:s');

        $this->actingAs($user)
            ->patch(route('reservations.cancel', $reservation))
            ->assertRedirect(route('reservations.index'));

        $reservation->refresh();
        $this->assertSame($original, $reservation->cancelled_at?->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('reservations', 4);
    }

    public function test_cancel_of_unknown_id_returns_404_and_persists_nothing(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->patch('/reservations/999999/cancel')
            ->assertNotFound();

        $this->actingAs($user)
            ->patch('/reservations/not-a-uuid/cancel')
            ->assertNotFound();

        $this->assertDatabaseCount('reservations', 3);
    }
}
