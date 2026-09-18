<?php

namespace Tests\Unit\Room;

use App\Modules\Room\Application\Errors\RoomNotFound;
use App\Modules\Room\Application\UseCases\FindRoom;
use App\Modules\Room\Domain\Entities\Room;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class FindRoomTest extends TestCase
{
    public function test_it_returns_the_room_when_the_port_finds_it(): void
    {
        $rooms = new FakeRoomRepository;
        $existing = new Room(
            id: '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            name: 'Sala Azul',
            capacity: 10,
            isActive: true,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
            deletedAt: null,
        );
        $rooms->seed($existing);

        $found = (new FindRoom($rooms))->execute($existing->id);

        $this->assertSame($existing, $found);
    }

    public function test_it_throws_room_not_found_when_the_port_returns_null(): void
    {
        $this->expectException(RoomNotFound::class);

        (new FindRoom(new FakeRoomRepository))->execute('missing-id');
    }
}
