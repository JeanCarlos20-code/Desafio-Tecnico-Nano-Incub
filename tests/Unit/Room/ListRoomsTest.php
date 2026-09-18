<?php

namespace Tests\Unit\Room;

use App\Modules\Room\Application\UseCases\ListRooms;
use App\Modules\Room\Domain\Entities\Room;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ListRoomsTest extends TestCase
{
    public function test_it_returns_non_deleted_rooms_in_id_order_for_the_requested_page_including_inactive(): void
    {
        $rooms = new FakeRoomRepository;
        $rooms->seed($this->room('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c12', 'Sala B', true));
        $rooms->seed($this->room('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11', 'Sala A', false));
        $rooms->seed($this->room('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c13', 'Sala C', true));

        $page = (new ListRooms($rooms))->execute(1, 2);

        $this->assertSame([['page' => 1, 'perPage' => 2]], $rooms->listed);
        $this->assertSame(3, $page['total']);
        $this->assertCount(2, $page['items']);
        $this->assertSame('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11', $page['items'][0]->id);
        $this->assertSame('Sala A', $page['items'][0]->name);
        $this->assertFalse($page['items'][0]->isActive);
        $this->assertSame('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c12', $page['items'][1]->id);
    }

    public function test_it_lists_page_1_when_the_requested_page_is_less_than_one(): void
    {
        $rooms = new FakeRoomRepository;
        $rooms->seed($this->room('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11', 'Sala A', true));

        (new ListRooms($rooms))->execute(0, 15);

        $this->assertSame([['page' => 1, 'perPage' => 15]], $rooms->listed);
    }

    private function room(string $id, string $name, bool $isActive): Room
    {
        return new Room(
            id: $id,
            name: $name,
            capacity: 8,
            isActive: $isActive,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
            deletedAt: null,
        );
    }
}
