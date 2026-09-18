<?php

namespace Tests\Feature\Room;

use App\Modules\Room\Infra\Database\Models\Room;
use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertTrue($ordered->first()->is($first) || $ordered->first()->is($second));

        $this->actingAs($user)
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Room/Index')
                ->has('rooms.data', 2)
                ->where('rooms.data.0.id', $ordered[0]->id)
                ->where('rooms.data.0.name', $ordered[0]->name)
                ->where('rooms.data.0.capacity', $ordered[0]->capacity)
                ->where('rooms.data.0.status', $ordered[0]->is_active ? 'Ativa' : 'Inativa')
                ->where('rooms.data.0.created_at', $ordered[0]->created_at->timezone(config('app.timezone'))->format('d/m/Y'))
                ->where('rooms.data.1.id', $ordered[1]->id)
                ->where('rooms.data.1.status', $ordered[1]->is_active ? 'Ativa' : 'Inativa')
                ->where('rooms.total', 2)
                ->where('rooms.per_page', 15)
            );

        $names = Room::query()->orderBy('id')->pluck('name')->all();
        $this->assertContains('Sala A', $names);
        $this->assertContains('Sala B', $names);
        $this->assertNotContains('Sala Excluida', $names);
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
                ->where('rooms.total', 16)
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
