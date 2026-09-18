<?php

namespace Tests\Feature\User;

use App\Modules\User\Application\UseCases\CreateUser;
use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateUserPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a_user_matching_the_adr_contract(): void
    {
        $user = $this->app->make(CreateUser::class)->execute(
            'Ada Lovelace',
            'ada@example.com',
            'secret123',
        );

        $model = UserModel::query()->findOrFail($user->id);

        $this->assertTrue(Str::isUuid($user->id));
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertSame('Ada Lovelace', $model->name);
        $this->assertSame('ada@example.com', $model->email);
        $this->assertNotSame('secret123', $user->passwordHash);
        $this->assertTrue(Hash::check('secret123', $user->passwordHash));
        $this->assertSame('argon2i', password_get_info($user->passwordHash)['algoName']);
        $this->assertNull($user->rememberToken);
        $this->assertNull($user->deletedAt);
        $this->assertNotNull($user->createdAt);
        $this->assertNotNull($user->updatedAt);

        $allowed = ['id', 'name', 'email', 'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at'];
        $this->assertSame([], array_values(array_diff(array_keys($model->getAttributes()), $allowed)));

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'deleted_at' => null,
            'remember_token' => null,
        ]);
    }

    public function test_eight_character_password_is_accepted(): void
    {
        $user = $this->app->make(CreateUser::class)->execute(
            'Grace Hopper',
            'grace@example.com',
            '12345678',
        );

        $this->assertTrue(Hash::check('12345678', $user->passwordHash));
        $this->assertDatabaseCount('users', 1);
    }
}
