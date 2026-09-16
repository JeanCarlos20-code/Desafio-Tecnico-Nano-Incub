<?php

namespace Tests\Unit\User;

use App\Modules\User\Application\UseCases\CreateUser;
use App\Modules\User\Domain\Entities\User;
use App\Modules\User\Domain\Repositories\UserRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CreateUserTest extends TestCase
{
    public function test_it_delegates_persistence_to_the_repository(): void
    {
        $users = new FakeUserRepository;
        $created = (new CreateUser($users))->execute('Ada Lovelace', 'ada@example.com', 'secret123');

        $this->assertSame('Ada Lovelace', $created->name);
        $this->assertSame('ada@example.com', $created->email);
        $this->assertSame(
            [['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'password' => 'secret123']],
            $users->created,
        );
    }
}

final class FakeUserRepository implements UserRepository
{
    /** @var list<array{name: string, email: string, password: string}> */
    public array $created = [];

    public function existsByEmail(string $email): bool
    {
        return false;
    }

    public function create(string $name, string $email, string $password): User
    {
        $this->created[] = compact('name', 'email', 'password');

        return new User(
            id: '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            name: $name,
            email: $email,
            passwordHash: 'hash-'.$password,
            rememberToken: null,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
            deletedAt: null,
        );
    }
}
