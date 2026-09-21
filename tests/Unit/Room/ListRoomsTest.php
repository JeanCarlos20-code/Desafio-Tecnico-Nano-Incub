<?php

namespace Tests\Unit\Room;

use App\Modules\Room\Application\UseCases\ListRooms;
use App\Modules\Room\Domain\Entities\Room;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ListRoomsTest extends TestCase
{
    public function test_it_forwards_status_all_active_inactive_clamps_page_and_reports_has_any(): void
    {
        $rooms = new FakeRoomRepository;
        $rooms->seed($this->room('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c12', 'Sala B', true));
        $rooms->seed($this->room('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11', 'Sala A', false));
        $rooms->seed($this->room('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c13', 'Sala C', true));

        $default = (new ListRooms($rooms))->execute(0, 2);
        $this->assertSame([['page' => 1, 'perPage' => 2, 'status' => 'active']], $rooms->listed);
        $this->assertTrue($default['hasAny']);
        $this->assertSame(2, $default['total']);
        $this->assertCount(2, $default['items']);
        $this->assertSame('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c12', $default['items'][0]->id);
        $this->assertTrue($default['items'][0]->isActive);

        $all = (new ListRooms($rooms))->execute(1, 2, 'all');
        $this->assertSame('all', $rooms->listed[1]['status']);
        $this->assertTrue($all['hasAny']);
        $this->assertSame(3, $all['total']);
        $this->assertCount(2, $all['items']);
        $this->assertSame('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11', $all['items'][0]->id);
        $this->assertFalse($all['items'][0]->isActive);

        $active = (new ListRooms($rooms))->execute(1, 15, 'active');
        $this->assertSame('active', $rooms->listed[2]['status']);
        $this->assertSame(2, $active['total']);
        $this->assertTrue($active['hasAny']);
        $this->assertTrue($active['items'][0]->isActive);

        $inactive = (new ListRooms($rooms))->execute(1, 15, 'inactive');
        $this->assertSame('inactive', $rooms->listed[3]['status']);
        $this->assertSame(1, $inactive['total']);
        $this->assertFalse($inactive['items'][0]->isActive);
        $this->assertTrue($inactive['hasAny']);
    }

    public function test_it_reports_has_any_false_when_no_rooms_exist(): void
    {
        $result = (new ListRooms(new FakeRoomRepository))->execute(1, 15, 'inactive');

        $this->assertFalse($result['hasAny']);
        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['items']);
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
