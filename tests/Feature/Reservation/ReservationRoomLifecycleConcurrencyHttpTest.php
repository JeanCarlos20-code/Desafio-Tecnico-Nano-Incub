<?php

namespace Tests\Feature\Reservation;

use App\Modules\Reservation\Infra\Database\Models\Reservation;
use App\Modules\Room\Infra\Database\Models\Room;
use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReservationRoomLifecycleConcurrencyHttpTest extends TestCase
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

    public function test_create_after_same_room_deactivation_fails_with_inactive_room_and_inserts_no_row(): void
    {
        [$output, $room] = $this->runLifecycleRace('concurrency_deactivate_worker.php');

        $this->assertSame(0, Reservation::query()->count());
        $this->assertFalse((bool) $room->fresh()->is_active);
        $this->assertStringContainsString('Não é possível reservar uma sala inativa.', $output);
    }

    public function test_create_after_same_room_deletion_fails_with_room_not_found_and_inserts_no_row(): void
    {
        [$output, $room] = $this->runLifecycleRace('concurrency_delete_worker.php');

        $this->assertSame(0, Reservation::query()->count());
        $this->assertSoftDeleted('rooms', ['id' => $room->id]);
        $this->assertStringContainsString('Sala não encontrada.', $output);
    }

    /**
     * @return array{0: string, 1: Room}
     */
    private function runLifecycleRace(string $lifecycleWorker): array
    {
        $user = UserModel::factory()->create();
        $room = Room::factory()->create(['capacity' => 8, 'is_active' => true]);

        $barrier = sys_get_temp_dir().'/reservation-lifecycle-barrier-'.uniqid('', true);
        $createWorker = base_path('tests/Feature/Reservation/Support/concurrency_create_after_lifecycle_worker.php');
        $lifecycle = base_path('tests/Feature/Reservation/Support/'.$lifecycleWorker);
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
            'SESSION_DRIVER' => 'array',
        ];

        $lifecycleProcess = new Process(
            [PHP_BINARY, $lifecycle, (string) $room->id, (string) $user->id, $barrier, 'life'],
            base_path(),
            $env,
        );
        $createProcess = new Process(
            [PHP_BINARY, $createWorker, (string) $room->id, (string) $user->id, $barrier, $startsAt, $endsAt, 'create'],
            base_path(),
            $env,
        );

        $lifecycleProcess->start();
        $createProcess->start();

        $readyDeadline = microtime(true) + 15;
        while (! is_file($barrier.'.ready.life') || ! is_file($barrier.'.ready.create')) {
            if (microtime(true) > $readyDeadline) {
                $this->fail(
                    'Workers did not become ready: '.$lifecycleProcess->getErrorOutput().' '.$createProcess->getErrorOutput()
                );
            }

            usleep(5000);
        }

        file_put_contents($barrier, 'go');
        $lifecycleProcess->wait();
        $createProcess->wait();

        @unlink($barrier);
        @unlink($barrier.'.ready.life');
        @unlink($barrier.'.ready.create');
        @unlink($barrier.'.lifecycle-started');
        @unlink($barrier.'.create-started');

        $this->assertTrue(
            $lifecycleProcess->isSuccessful() && $createProcess->isSuccessful(),
            $lifecycleProcess->getErrorOutput()."\n".$createProcess->getErrorOutput(),
        );

        return [$createProcess->getOutput(), $room];
    }
}
