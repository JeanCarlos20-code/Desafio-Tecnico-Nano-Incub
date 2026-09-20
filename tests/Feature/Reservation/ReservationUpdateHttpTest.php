<?php

namespace Tests\Feature\Reservation;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReservationUpdateHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(now()->timezone((string) config('app.timezone'))->setDate(2026, 9, 21)->setTime(8, 0));
    }

    public function test_authenticated_put_updates_only_title_and_responsible_on_mysql(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create(['name' => 'Sala Azul']);
        $reservation = Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Daily',
            'responsible' => 'Ada',
            'starts_at' => '2026-09-21 10:00:00',
            'ends_at' => '2026-09-21 10:30:00',
            'participants' => 3,
            'cancelled_at' => null,
        ]);
        $before = $this->occupancySnapshot($reservation);

        $this->actingAs($user)
            ->put(route('reservations.update', $reservation), [
                'title' => '  Daily revisada  ',
                'responsible' => '  Ada Lovelace  ',
            ])
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('success', 'Reserva atualizada com sucesso.');

        $reservation->refresh();
        $this->assertSame('Daily revisada', $reservation->title);
        $this->assertSame('Ada Lovelace', $reservation->responsible);
        $this->assertSame($before, $this->occupancySnapshot($reservation));
    }

    public function test_authenticated_put_with_a_prohibited_occupancy_key_is_rejected_and_writes_nothing(): void
    {
        $user = UserModel::factory()->create();
        $reservation = Reservation::factory()->create([
            'title' => 'Daily',
            'responsible' => 'Ada',
            'starts_at' => '2026-09-21 10:00:00',
            'ends_at' => '2026-09-21 10:30:00',
            'participants' => 3,
        ]);
        $before = $reservation->only(['title', 'responsible', 'starts_at', 'ends_at', 'room_id', 'participants', 'cancelled_at']);

        $this->actingAs($user)
            ->from(route('reservations.edit', $reservation))
            ->put(route('reservations.update', $reservation), [
                'title' => 'Daily revisada',
                'responsible' => 'Ada Lovelace',
                'starts_at' => '2026-09-22 11:00:00',
            ])
            ->assertSessionHasErrors('starts_at');

        $reservation->refresh();
        $this->assertSame('Daily', $reservation->title);
        $this->assertSame('Ada', $reservation->responsible);
        $this->assertSame(
            $before['starts_at']?->format('Y-m-d H:i:s'),
            $reservation->starts_at?->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            $before['ends_at']?->format('Y-m-d H:i:s'),
            $reservation->ends_at?->format('Y-m-d H:i:s'),
        );
        $this->assertSame($before['room_id'], $reservation->room_id);
        $this->assertSame($before['participants'], $reservation->participants);
        $this->assertNull($reservation->cancelled_at);
    }

    public function test_authenticated_get_or_put_for_a_cancelled_or_missing_reservation_returns_404(): void
    {
        $user = UserModel::factory()->create();
        $cancelled = Reservation::factory()->create([
            'title' => 'Cancelada',
            'responsible' => 'Ada',
            'starts_at' => '2026-09-21 10:00:00',
            'ends_at' => '2026-09-21 10:30:00',
            'cancelled_at' => '2026-09-21 07:00:00',
        ]);
        $before = $this->rowSnapshot($cancelled);

        $this->actingAs($user)
            ->get(route('reservations.edit', $cancelled))
            ->assertNotFound();

        $this->actingAs($user)
            ->put(route('reservations.update', $cancelled), [
                'title' => 'Nao deve gravar',
                'responsible' => 'Intruso',
            ])
            ->assertNotFound();

        $this->assertSame($before, $this->rowSnapshot($cancelled->fresh()));

        $this->actingAs($user)
            ->get('/reservations/999999/edit')
            ->assertNotFound();

        $this->actingAs($user)
            ->put('/reservations/999999', [
                'title' => 'Ausente',
                'responsible' => 'Ada',
            ])
            ->assertNotFound();
    }

    public function test_authenticated_get_edit_renders_inertia_reservation_edit_with_current_values(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create(['name' => 'Sala Azul']);
        $reservation = Reservation::factory()->create([
            'room_id' => $room->id,
            'title' => 'Daily',
            'responsible' => 'Ada Lovelace',
            'starts_at' => '2026-09-21 10:00:00',
            'ends_at' => '2026-09-21 10:30:00',
            'participants' => 4,
        ]);

        $this->actingAs($user)
            ->get(route('reservations.edit', $reservation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reservation/Edit')
                ->where('id', (string) $reservation->id)
                ->where('title', 'Daily')
                ->where('responsible', 'Ada Lovelace')
                ->where('room_id', (string) $room->id)
                ->where('room_name', 'Sala Azul')
                ->where('date', '2026-09-21')
                ->where('start_time', '10:00')
                ->where('end_time', '10:30')
                ->where('participants', 4)
            );
    }

    public function test_authenticated_index_lists_an_active_id_whose_edit_route_is_reachable(): void
    {
        $user = UserModel::factory()->create();
        $reservation = Reservation::factory()->create([
            'title' => 'Alvo da lista',
            'starts_at' => '2026-09-21 15:00:00',
            'ends_at' => '2026-09-21 15:30:00',
        ]);

        $this->actingAs($user)
            ->get(route('reservations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reservation/Index')
                ->where('reservations.data', fn ($rows): bool => collect($rows)->contains('id', (string) $reservation->id))
            );

        $this->actingAs($user)
            ->get(route('reservations.edit', $reservation->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Reservation/Edit'));
    }

    /**
     * @return array{starts_at: string, ends_at: string, room_id: mixed, participants: int, cancelled_at: ?string}
     */
    private function occupancySnapshot(Reservation $reservation): array
    {
        return [
            'starts_at' => $reservation->starts_at?->format('Y-m-d H:i:s'),
            'ends_at' => $reservation->ends_at?->format('Y-m-d H:i:s'),
            'room_id' => $reservation->room_id,
            'participants' => $reservation->participants,
            'cancelled_at' => $reservation->cancelled_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rowSnapshot(Reservation $reservation): array
    {
        return [
            'title' => $reservation->title,
            'responsible' => $reservation->responsible,
            ...$this->occupancySnapshot($reservation),
        ];
    }
}
