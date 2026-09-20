<?php

namespace Tests\Feature\Reservation;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReservationConcurrencyHttpTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string|null>
     */
    protected array $connectionsToTransact = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_two_concurrent_posts_for_the_same_overlapping_room_persist_exactly_one_active_row(): void
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create(['capacity' => 8, 'is_active' => true]);

        $barrier = sys_get_temp_dir().'/reservation-barrier-'.uniqid('', true);
        $worker = base_path('tests/Feature/Reservation/Support/concurrency_create_worker.php');
        $startsAt = '2026-12-01 10:00:00';
        $endsAt = '2026-12-01 10:30:00';
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => (string) config('database.connections.mysql.database'),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
        ];

        $first = new Process(
            [PHP_BINARY, $worker, (string) $room->id, (string) $user->id, $barrier, $startsAt, $endsAt, 'a'],
            base_path(),
            $env,
        );
        $second = new Process(
            [PHP_BINARY, $worker, (string) $room->id, (string) $user->id, $barrier, $startsAt, $endsAt, 'b'],
            base_path(),
            $env,
        );

        $first->start();
        $second->start();

        $readyDeadline = microtime(true) + 15;
        while (! is_file($barrier.'.ready.a') || ! is_file($barrier.'.ready.b')) {
            if (microtime(true) > $readyDeadline) {
                $this->fail(
                    'Workers did not become ready: '.$first->getErrorOutput().' '.$second->getErrorOutput()
                );
            }

            usleep(5000);
        }

        file_put_contents($barrier, 'go');
        $first->wait();
        $second->wait();

        @unlink($barrier);
        @unlink($barrier.'.ready.a');
        @unlink($barrier.'.ready.b');

        $this->assertTrue(
            $first->isSuccessful() && $second->isSuccessful(),
            $first->getErrorOutput()."\n".$second->getErrorOutput(),
        );
        $this->assertSame(1, Reservation::query()->where('room_id', $room->id)->whereNull('cancelled_at')->count());
        $this->assertSame(4, Reservation::query()->whereNull('cancelled_at')->count());
        $this->assertDatabaseCount('reservations', 4);
    }
}
