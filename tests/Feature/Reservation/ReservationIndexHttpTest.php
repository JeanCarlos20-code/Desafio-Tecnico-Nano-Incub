<?php

namespace Tests\Feature\Reservation;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReservationIndexHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(now()->timezone((string) config('app.timezone'))->setDate(2026, 9, 21)->setTime(12, 0));
    }

    public function test_authenticated_index_renders_reservation_index_with_default_date_today_and_starts_at_asc(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create(['name' => 'Sala Azul']);

        $later = Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Tarde',
            'starts_at' => '2026-09-21 11:00:00',
            'ends_at' => '2026-09-21 11:30:00',
        ]);
        $earlier = Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Manha',
            'starts_at' => '2026-09-21 09:00:00',
            'ends_at' => '2026-09-21 09:30:00',
        ]);
        Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Amanha',
            'starts_at' => '2026-09-22 09:00:00',
            'ends_at' => '2026-09-22 09:30:00',
        ]);

        $this->actingAs($user)
            ->get(route('reservations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reservation/Index')
                ->where('filters.date', '2026-09-21')
                ->where('filters.room_id', null)
                ->where('reservations.per_page', 15)
                ->has('reservations.data', 2)
                ->where('reservations.data.0.id', $earlier->id)
                ->where('reservations.data.0.title', 'Manha')
                ->where('reservations.data.0.starts_at', '09:00')
                ->where('reservations.data.1.id', $later->id)
                ->where('reservations.data.1.title', 'Tarde')
            );
    }

    public function test_index_filters_by_room_and_local_day_including_canceled_rows(): void
    {
        $user = UserModel::factory()->create();
        $roomA = Room::factory()->create(['name' => 'Sala A']);
        $roomB = Room::factory()->create(['name' => 'Sala B']);

        $canceled = Reservation::factory()->create([
            'room_id' => $roomA->id,
            'title' => 'Cancelada A',
            'starts_at' => '2026-09-21 09:00:00',
            'ends_at' => '2026-09-21 09:30:00',
            'cancelled_at' => '2026-09-21 08:00:00',
        ]);
        $active = Reservation::factory()->create([
            'room_id' => $roomA->id,
            'title' => 'Ativa A',
            'starts_at' => '2026-09-21 10:00:00',
            'ends_at' => '2026-09-21 10:30:00',
        ]);
        Reservation::factory()->create([
            'room_id' => $roomB->id,
            'title' => 'Outra sala',
            'starts_at' => '2026-09-21 09:00:00',
            'ends_at' => '2026-09-21 09:30:00',
        ]);
        Reservation::factory()->create([
            'room_id' => $roomA->id,
            'title' => 'Outro dia',
            'starts_at' => '2026-09-22 09:00:00',
            'ends_at' => '2026-09-22 09:30:00',
        ]);

        $this->actingAs($user)
            ->get('/reservations?room_id='.$roomA->id.'&date=2026-09-21')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reservation/Index')
                ->where('filters.room_id', $roomA->id)
                ->where('filters.date', '2026-09-21')
                ->has('reservations.data', 2)
                ->where('reservations.data.0.id', $canceled->id)
                ->where('reservations.data.0.status', 'canceled')
                ->where('reservations.data.0.status_label', 'Cancelada')
                ->where('reservations.data.1.id', $active->id)
                ->where('reservations.data.1.status', 'active')
                ->where('reservations.data.1.status_label', 'Ativa')
            );
    }
}
