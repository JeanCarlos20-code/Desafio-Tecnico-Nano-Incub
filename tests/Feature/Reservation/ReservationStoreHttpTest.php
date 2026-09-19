<?php

namespace Tests\Feature\Reservation;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReservationStoreHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(now()->timezone((string) config('app.timezone'))->setDate(2026, 9, 21)->setTime(8, 0));
    }

    public function test_create_page_renders_only_active_rooms(): void
    {
        $user = UserModel::factory()->create();
        $active = Room::factory()->create(['name' => 'Sala Azul', 'is_active' => true]);
        Room::factory()->create(['name' => 'Sala Cinza', 'is_active' => false]);

        $this->actingAs($user)
            ->get(route('reservations.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reservation/Create')
                ->has('rooms', 1)
                ->where('rooms.0.id', $active->id)
                ->where('rooms.0.name', 'Sala Azul')
            );
    }

    public function test_store_persists_uuid_v7_adr_005_columns_and_flashes_success(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $this->actingAs($user)
            ->from(route('reservations.create'))
            ->post(route('reservations.store'), $this->payload($room->id))
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('success', 'Reserva criada com sucesso.');

        $this->assertDatabaseCount('reservations', 1);

        $reservation = Reservation::query()->first();
        $this->assertNotNull($reservation);
        $this->assertTrue(Str::isUuid($reservation->id));
        $this->assertSame('7', $reservation->id[14]);
        $this->assertSame($room->id, $reservation->room_id);
        $this->assertSame('Ada Lovelace', $reservation->responsible);
        $this->assertSame('Daily', $reservation->title);
        $this->assertSame('2026-09-21 10:00:00', $reservation->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-21 10:30:00', $reservation->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame(2, $reservation->participants);
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
    }

    public function test_store_rejects_invalid_form_request_input_and_persists_nothing(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->from(route('reservations.create'))
            ->post(route('reservations.store'), [
                'participants' => 2,
            ])
            ->assertRedirect(route('reservations.create'))
            ->assertSessionHasErrors([
                'room_id' => 'Informe a sala.',
                'responsible' => 'Informe o responsável.',
                'title' => 'Informe o título da reserva.',
                'starts_at' => 'Informe o início.',
                'ends_at' => 'Informe o término.',
            ]);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_store_rejects_inactive_room_over_capacity_past_start_bad_duration_and_active_overlap(): void
    {
        $user = UserModel::factory()->create();
        $inactive = Room::factory()->create(['name' => 'Inativa', 'is_active' => false, 'capacity' => 8]);
        $small = Room::factory()->create(['name' => 'Pequena', 'is_active' => true, 'capacity' => 2]);
        $room = Room::factory()->create(['name' => 'Azul', 'is_active' => true, 'capacity' => 8]);

        Reservation::factory()->create([
            'room_id' => $room->id,
            'starts_at' => '2026-09-21 10:00:00',
            'ends_at' => '2026-09-21 10:30:00',
        ]);

        $this->actingAs($user)
            ->from(route('reservations.create'))
            ->post(route('reservations.store'), $this->payload($inactive->id))
            ->assertRedirect(route('reservations.create'))
            ->assertSessionHasErrors(['room_id' => 'Não é possível reservar uma sala inativa.']);

        $this->actingAs($user)
            ->from(route('reservations.create'))
            ->post(route('reservations.store'), $this->payload($small->id, ['participants' => 3]))
            ->assertRedirect(route('reservations.create'))
            ->assertSessionHasErrors(['participants' => 'O número de participantes excede a capacidade da sala.']);

        $this->actingAs($user)
            ->from(route('reservations.create'))
            ->post(route('reservations.store'), $this->payload($room->id, [
                'starts_at' => '2026-09-21 07:00:00',
                'ends_at' => '2026-09-21 07:30:00',
            ]))
            ->assertRedirect(route('reservations.create'))
            ->assertSessionHasErrors(['starts_at' => 'O horário inicial não pode estar no passado.']);

        $this->actingAs($user)
            ->from(route('reservations.create'))
            ->post(route('reservations.store'), $this->payload($room->id, [
                'starts_at' => '2026-09-21 14:00:00',
                'ends_at' => '2026-09-21 14:10:00',
            ]))
            ->assertRedirect(route('reservations.create'))
            ->assertSessionHasErrors(['ends_at' => 'A reserva deve durar entre 30 minutos e 4 horas.']);

        $this->actingAs($user)
            ->from(route('reservations.create'))
            ->post(route('reservations.store'), $this->payload($room->id, [
                'starts_at' => '2026-09-21 10:15:00',
                'ends_at' => '2026-09-21 10:45:00',
            ]))
            ->assertRedirect(route('reservations.create'))
            ->assertSessionHasErrors(['starts_at' => 'Já existe uma reserva ativa neste horário para a sala.']);

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_store_accepts_a_consecutive_slot_and_a_slot_that_only_overlaps_a_canceled_row(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create(['capacity' => 8]);

        Reservation::factory()->create([
            'room_id' => $room->id,
            'starts_at' => '2026-09-21 10:00:00',
            'ends_at' => '2026-09-21 10:30:00',
        ]);
        Reservation::factory()->create([
            'room_id' => $room->id,
            'starts_at' => '2026-09-21 14:00:00',
            'ends_at' => '2026-09-21 14:30:00',
            'cancelled_at' => '2026-09-21 08:00:00',
        ]);

        $this->actingAs($user)
            ->post(route('reservations.store'), $this->payload($room->id, [
                'title' => 'Consecutiva',
                'starts_at' => '2026-09-21 10:30:00',
                'ends_at' => '2026-09-21 11:00:00',
            ]))
            ->assertRedirect(route('reservations.index'));

        $this->actingAs($user)
            ->post(route('reservations.store'), $this->payload($room->id, [
                'title' => 'Sobre cancelada',
                'starts_at' => '2026-09-21 14:00:00',
                'ends_at' => '2026-09-21 14:30:00',
            ]))
            ->assertRedirect(route('reservations.index'));

        $this->assertDatabaseCount('reservations', 4);
        $this->assertDatabaseHas('reservations', ['title' => 'Consecutiva']);
        $this->assertDatabaseHas('reservations', ['title' => 'Sobre cancelada']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $roomId, array $overrides = []): array
    {
        return array_merge([
            'room_id' => $roomId,
            'responsible' => 'Ada Lovelace',
            'title' => 'Daily',
            'starts_at' => '2026-09-21 10:00:00',
            'ends_at' => '2026-09-21 10:30:00',
            'participants' => 2,
        ], $overrides);
    }
}
