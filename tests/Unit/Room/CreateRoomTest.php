<?php

namespace Tests\Unit\Room;

use App\Modules\Room\Application\UseCases\CreateRoom;
use PHPUnit\Framework\TestCase;

class CreateRoomTest extends TestCase
{
    public function test_it_persists_name_capacity_and_is_active_through_the_repository(): void
    {
        $rooms = new FakeRoomRepository;
        $created = (new CreateRoom($rooms))->execute('Sala Azul', 12, false);

        $this->assertSame('Sala Azul', $created->name);
        $this->assertSame(12, $created->capacity);
        $this->assertFalse($created->isActive);
        $this->assertSame(
            [['name' => 'Sala Azul', 'capacity' => 12, 'is_active' => false]],
            $rooms->created,
        );
    }

    public function test_it_treats_omitted_is_active_as_true(): void
    {
        $rooms = new FakeRoomRepository;
        $created = (new CreateRoom($rooms))->execute('Sala Verde', 8);

        $this->assertTrue($created->isActive);
        $this->assertSame(
            [['name' => 'Sala Verde', 'capacity' => 8, 'is_active' => true]],
            $rooms->created,
        );
    }
}
