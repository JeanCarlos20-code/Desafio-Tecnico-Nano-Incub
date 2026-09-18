<?php

namespace Tests\Unit\Room;

use App\Modules\Room\Application\Errors\RoomNotFound;
use App\Modules\Room\Application\UseCases\UpdateRoom;
use App\Modules\Room\Domain\Entities\Room;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class UpdateRoomTest extends TestCase
{
    public function test_it_persists_is_active_false_when_the_caller_confirms_deactivation(): void
    {
        $rooms = new FakeRoomRepository;
        $rooms->seed($this->room(isActive: true));

        $updated = (new UpdateRoom($rooms))->execute(
            '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            'Sala Verde',
            20,
            false,
        );

        $this->assertSame('Sala Verde', $updated->name);
        $this->assertSame(20, $updated->capacity);
        $this->assertFalse($updated->isActive);
        $this->assertSame([
            [
                'id' => '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
                'name' => 'Sala Verde',
                'capacity' => 20,
                'is_active' => false,
            ],
        ], $rooms->updated);
    }

    public function test_it_keeps_the_stored_is_active_when_the_caller_omits_status(): void
    {
        $rooms = new FakeRoomRepository;
        $rooms->seed($this->room(isActive: false));

        $updated = (new UpdateRoom($rooms))->execute(
            '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            'Sala Verde',
            20,
        );

        $this->assertSame('Sala Verde', $updated->name);
        $this->assertSame(20, $updated->capacity);
        $this->assertFalse($updated->isActive);
        $this->assertSame([
            [
                'id' => '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
                'name' => 'Sala Verde',
                'capacity' => 20,
                'is_active' => false,
            ],
        ], $rooms->updated);
    }

    public function test_it_throws_room_not_found_when_the_room_is_missing(): void
    {
        $rooms = new FakeRoomRepository;

        try {
            (new UpdateRoom($rooms))->execute('missing-id', 'Sala', 8, true);
            $this->fail('Expected RoomNotFound');
        } catch (RoomNotFound) {
            $this->assertSame([], $rooms->updated);
        }
    }

    private function room(bool $isActive): Room
    {
        return new Room(
            id: '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            name: 'Sala Azul',
            capacity: 10,
            isActive: $isActive,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
            deletedAt: null,
        );
    }
}
