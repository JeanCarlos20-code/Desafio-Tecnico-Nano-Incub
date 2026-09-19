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

    public function test_authenticated_index_lists_every_active_row_and_echoes_period_all(): void
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
        $tomorrow = Reservation::factory()->create([
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
                ->where('filters.period', 'all')
                ->where('filters.starts_on', null)
                ->where('filters.ends_on', null)
                ->where('filters.room_id', null)
                ->where('reservations.per_page', 15)
                ->has('reservations.data', 3)
                ->where('reservations.data.0.id', $earlier->id)
                ->where('reservations.data.0.title', 'Manha')
                ->where('reservations.data.0.starts_at', '21/09/2026 09:00')
                ->where('reservations.data.1.id', $later->id)
                ->where('reservations.data.1.title', 'Tarde')
                ->where('reservations.data.2.id', $tomorrow->id)
            );
    }

    public function test_index_period_presets_return_only_actives_in_the_local_window(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create();

        $sep20 = Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Sep20',
            'starts_at' => '2026-09-20 09:00:00',
            'ends_at' => '2026-09-20 09:30:00',
        ]);
        $sep21 = Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Sep21',
            'starts_at' => '2026-09-21 09:00:00',
            'ends_at' => '2026-09-21 09:30:00',
        ]);
        $sep22 = Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Sep22',
            'starts_at' => '2026-09-22 09:00:00',
            'ends_at' => '2026-09-22 09:30:00',
        ]);
        $sep27 = Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Sep27',
            'starts_at' => '2026-09-27 09:00:00',
            'ends_at' => '2026-09-27 09:30:00',
        ]);
        $sep28 = Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Sep28',
            'starts_at' => '2026-09-28 00:00:00',
            'ends_at' => '2026-09-28 00:30:00',
        ]);

        $this->actingAs($user)
            ->get('/reservations?period=today')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.period', 'today')
                ->has('reservations.data', 1)
                ->where('reservations.data.0.id', $sep21->id)
                ->where('reservations.data.0.starts_at', '09:00')
            );

        $this->actingAs($user)
            ->get('/reservations?period=tomorrow')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reservations.data', 1)
                ->where('reservations.data.0.id', $sep22->id)
            );

        $this->actingAs($user)
            ->get('/reservations?period=week')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reservations.data', 3)
                ->where('reservations.data.0.id', $sep21->id)
                ->where('reservations.data.1.id', $sep22->id)
                ->where('reservations.data.2.id', $sep27->id)
            );

        $this->assertDatabaseHas('reservations', ['id' => $sep20->id]);
        $this->assertDatabaseHas('reservations', ['id' => $sep28->id]);
    }

    public function test_index_range_overrides_period_today_and_is_inclusive(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create();

        Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Today',
            'starts_at' => '2026-09-21 09:00:00',
            'ends_at' => '2026-09-21 09:30:00',
        ]);
        $sep22 = Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Sep22',
            'starts_at' => '2026-09-22 09:00:00',
            'ends_at' => '2026-09-22 09:30:00',
        ]);
        $sep23 = Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Sep23',
            'starts_at' => '2026-09-23 23:59:59',
            'ends_at' => '2026-09-24 00:29:59',
        ]);
        Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Sep24',
            'starts_at' => '2026-09-24 00:00:00',
            'ends_at' => '2026-09-24 00:30:00',
        ]);

        $this->actingAs($user)
            ->get('/reservations?period=today&starts_on=2026-09-22&ends_on=2026-09-23')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.period', 'today')
                ->where('filters.starts_on', '2026-09-22')
                ->where('filters.ends_on', '2026-09-23')
                ->has('reservations.data', 2)
                ->where('reservations.data.0.id', $sep22->id)
                ->where('reservations.data.1.id', $sep23->id)
            );
    }

    public function test_index_pagination_links_keep_period_range_and_room_id(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create();

        Reservation::factory()->count(16)->create([
            'room_id' => $room->id,
            'starts_at' => '2026-09-21 09:00:00',
            'ends_at' => '2026-09-21 09:30:00',
        ]);

        $query = 'period=today&starts_on=2026-09-21&ends_on=2026-09-21&room_id='.$room->id;

        $this->actingAs($user)
            ->get('/reservations?'.$query.'&page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.period', 'today')
                ->where('filters.starts_on', '2026-09-21')
                ->where('filters.ends_on', '2026-09-21')
                ->where('filters.room_id', $room->id)
                ->where('reservations.per_page', 15)
                ->where('reservations.current_page', 2)
                ->has('reservations.data', 1)
                ->where('reservations.prev_page_url', function (?string $url) use ($room): bool {
                    return is_string($url)
                        && str_contains($url, 'period=today')
                        && str_contains($url, 'starts_on=2026-09-21')
                        && str_contains($url, 'ends_on=2026-09-21')
                        && str_contains($url, 'room_id='.$room->id);
                })
            );
    }

    public function test_index_unknown_period_or_one_sided_range_returns_422(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create();
        Reservation::factory()->create([
            'room_id' => $room->id,
            'starts_at' => '2026-09-21 09:00:00',
            'ends_at' => '2026-09-21 09:30:00',
        ]);

        $this->actingAs($user)
            ->getJson('/reservations?period=weekend')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['period']);

        $this->actingAs($user)
            ->getJson('/reservations?starts_on=2026-09-21')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ends_on']);

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_index_filters_by_room_with_the_resolved_window_and_excludes_canceled_rows(): void
    {
        $user = UserModel::factory()->create();
        $roomA = Room::factory()->create(['name' => 'Sala A']);
        $roomB = Room::factory()->create(['name' => 'Sala B']);

        Reservation::factory()->create([
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
            ->get('/reservations?room_id='.$roomA->id.'&period=today')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reservation/Index')
                ->where('filters.room_id', $roomA->id)
                ->where('filters.period', 'today')
                ->has('reservations.data', 1)
                ->where('reservations.data.0.id', $active->id)
                ->where('reservations.data.0.status', 'active')
                ->where('reservations.data.0.status_label', 'Ativa')
            );
    }

    public function test_index_hides_rows_canceled_by_standalone_deactivate_and_delete(): void
    {
        $user = UserModel::factory()->create();
        $deactivateRoom = Room::factory()->create(['is_active' => true]);
        $deleteRoom = Room::factory()->create(['is_active' => true]);
        $standalone = Reservation::factory()->create([
            'room_id' => $deactivateRoom->id,
            'title' => 'Standalone',
            'starts_at' => '2026-09-21 09:00:00',
            'ends_at' => '2026-09-21 09:30:00',
        ]);
        $toCancelOnDeactivate = Reservation::factory()->create([
            'room_id' => $deactivateRoom->id,
            'title' => 'Deactivate',
            'starts_at' => '2026-09-22 10:00:00',
            'ends_at' => '2026-09-22 10:30:00',
        ]);
        $toCancelOnDelete = Reservation::factory()->create([
            'room_id' => $deleteRoom->id,
            'title' => 'Delete',
            'starts_at' => '2026-09-21 11:00:00',
            'ends_at' => '2026-09-21 11:30:00',
        ]);

        $this->actingAs($user)
            ->from(route('reservations.index'))
            ->patch(route('reservations.cancel', $standalone))
            ->assertRedirect();

        $this->actingAs($user)
            ->put(route('rooms.update', $deactivateRoom), [
                'name' => $deactivateRoom->name,
                'capacity' => $deactivateRoom->capacity,
                'is_active' => false,
                'scheduled_meetings_action' => 'cancel',
            ]);

        $this->actingAs($user)
            ->delete(route('rooms.destroy', $deleteRoom));

        $this->actingAs($user)
            ->get('/reservations?period=all')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reservation/Index')
                ->has('reservations.data', 0)
                ->where('hasAny', false)
            );

        $this->assertNotNull($standalone->fresh()->cancelled_at);
        $this->assertNotNull($toCancelOnDeactivate->fresh()->cancelled_at);
        $this->assertNotNull($toCancelOnDelete->fresh()->cancelled_at);
    }
}
