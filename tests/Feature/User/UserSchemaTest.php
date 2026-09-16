<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_persists_user_matching_users_table_columns(): void
    {
        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret123',
        ]);

        $user->refresh();

        $this->assertTrue(Str::isUuid($user->id));
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertNotSame('secret123', $user->getAuthPassword());
        $this->assertTrue(Hash::check('secret123', $user->getAuthPassword()));
        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);

        $allowed = ['id', 'name', 'email', 'password', 'remember_token', 'created_at', 'updated_at'];
        $this->assertSame([], array_values(array_diff(array_keys($user->getAttributes()), $allowed)));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);
    }
}
