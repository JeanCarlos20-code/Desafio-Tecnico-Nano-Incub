<?php

namespace Tests\Unit\Room;

use App\Modules\Room\Application\Errors\RoomNotFound;
use App\Modules\Room\Application\UseCases\DeleteRoom;
use App\Modules\Room\Domain\Entities\Room;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DeleteRoomTest extends TestCase
{
    public function test_it_calls_repository_delete_when_the_room_exists(): void
    {
        $rooms = new FakeRoomRepository;
        $rooms->seed(new Room(
            id: '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            name: 'Sala Azul',
            capacity: 10,
            isActive: true,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
            deletedAt: null,
        ));

        (new DeleteRoom($rooms))->execute('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11');

        $this->assertSame(['018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11'], $rooms->deleted);
    }

    public function test_it_throws_room_not_found_when_the_room_is_missing(): void
    {
        $rooms = new FakeRoomRepository;

        try {
            (new DeleteRoom($rooms))->execute('missing-id');
            $this->fail('Expected RoomNotFound');
        } catch (RoomNotFound) {
            $this->assertSame([], $rooms->deleted);
        }
    }
}
