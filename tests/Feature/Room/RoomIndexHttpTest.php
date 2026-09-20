<?php

namespace Tests\Feature\Room;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoomIndexHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_authenticated_index_renders_non_deleted_rooms_including_inactive_excluding_soft_deleted_ordered_by_id(): void
    {
        $user = UserModel::factory()->create();
        $first = Room::factory()->create([
            'name' => 'Sala A',
            'capacity' => 4,
            'is_active' => false,
        ]);
        $second = Room::factory()->create([
            'name' => 'Sala B',
            'capacity' => 8,
            'is_active' => true,
        ]);
        $trashed = Room::factory()->create([
            'name' => 'Sala Excluida',
        ]);
        $trashed->delete();

        $ordered = Room::query()->orderBy('id')->get();
        $this->assertCount(5, $ordered);
        $firstHasReservations = Reservation::query()->where('room_id', $ordered[0]->id)->exists();

        $this->actingAs($user)
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Room/Index')
                ->has('rooms.data', 5)
                ->where('rooms.data.0.id', (string) $ordered[0]->id)
                ->where('rooms.data.0.name', $ordered[0]->name)
                ->where('rooms.data.0.capacity', $ordered[0]->capacity)
                ->where('rooms.data.0.status', $ordered[0]->is_active ? 'Ativa' : 'Inativa')
                ->where('rooms.data.0.created_at', $ordered[0]->created_at->timezone(config('app.timezone'))->format('d/m/Y'))
                ->where('rooms.data.1.id', (string) $ordered[1]->id)
                ->where('rooms.data.1.status', $ordered[1]->is_active ? 'Ativa' : 'Inativa')
                ->where('rooms.total', 5)
                ->where('rooms.per_page', 15)
                ->where('filters.status', 'all')
                ->where('hasAny', true)
                ->where('rooms.data.0.has_reservations', $firstHasReservations)
                ->where('rooms.data', function (Collection $rows) use ($first, $second): bool {
                    $ids = $rows->pluck('id');

                    return $ids->contains($first->id) && $ids->contains($second->id);
                })
            );

        $names = Room::query()->orderBy('id')->pluck('name')->all();
        $this->assertContains('Sala A', $names);
        $this->assertContains('Sala B', $names);
        $this->assertNotContains('Sala Excluida', $names);
    }

    public function test_index_filters_status_all_active_inactive_and_omits_soft_deleted_rooms(): void
    {
        $user = UserModel::factory()->create();
        $active = Room::factory()->create(['name' => 'Ativa', 'is_active' => true]);
        $inactive = Room::factory()->create(['name' => 'Inativa', 'is_active' => false]);
        $trashed = Room::factory()->create(['name' => 'Excluida']);
        $trashed->delete();

        $this->actingAs($user)
            ->get('/rooms?status=all')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Room/Index')
                ->where('filters.status', 'all')
                ->has('rooms.data', 5)
                ->where('hasAny', true)
            );

        $this->actingAs($user)
            ->get('/rooms?status=active')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'active')
                ->has('rooms.data', 4)
                ->where('rooms.data', fn (Collection $rooms): bool => $rooms->contains('id', $active->id))
                ->where('hasAny', true)
            );

        $this->actingAs($user)
            ->get('/rooms?status=inactive')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'inactive')
                ->has('rooms.data', 1)
                ->where('rooms.data.0.id', (string) $inactive->id)
            );

        $this->actingAs($user)
            ->get('/rooms?status=archived')
            ->assertRedirect()
            ->assertSessionHasErrors(['status']);
    }

    public function test_index_paginates_by_15_and_keeps_query_parameters_on_links(): void
    {
        $user = UserModel::factory()->create();
        Room::factory()->count(16)->create();

        $this->actingAs($user)
            ->get('/rooms?foo=bar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Room/Index')
                ->has('rooms.data', 15)
                ->where('rooms.total', 19)
                ->where('rooms.per_page', 15)
                ->where('rooms.current_page', 1)
                ->where('rooms.next_page_url', function (?string $url): bool {
                    return is_string($url)
                        && str_contains($url, 'foo=bar')
                        && str_contains($url, 'page=2');
                })
            );
    }

    public function test_index_shares_authenticated_administrator_name_and_does_not_share_a_password(): void
    {
        $user = UserModel::factory()->create([
            'name' => 'Ada Lovelace',
            'password' => 'secret123',
        ]);

        $this->actingAs($user)
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Room/Index')
                ->where('auth.user.name', 'Ada Lovelace')
                ->missing('auth.user.password')
            );
    }
}
