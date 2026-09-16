<?php

namespace Tests\Feature\User;

use App\Actions\CreateUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a_user_matching_the_users_table(): void
    {
        $user = (new CreateUser)->execute([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret123',
            'email_verified_at' => now(),
        ]);

        $user->refresh();

        $this->assertTrue(Str::isUuid($user->id));
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertNotSame('secret123', $user->getAuthPassword());
        $this->assertTrue(Hash::check('secret123', $user->getAuthPassword()));
        $this->assertNull($user->remember_token);
        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);

        $allowed = ['id', 'name', 'email', 'password', 'remember_token', 'created_at', 'updated_at'];
        $this->assertSame([], array_values(array_diff(array_keys($user->getAttributes()), $allowed)));

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);
    }

    public function test_eight_character_password_is_accepted(): void
    {
        $user = (new CreateUser)->execute([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'password' => '12345678',
        ]);

        $this->assertTrue(Hash::check('12345678', $user->getAuthPassword()));
        $this->assertDatabaseCount('users', 1);
    }

    #[DataProvider('missingRequiredFields')]
    public function test_it_rejects_missing_required_fields(string $field, array $payload): void
    {
        try {
            (new CreateUser)->execute($payload);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
        ]);

        try {
            (new CreateUser)->execute([
                'name' => 'Ada Two',
                'email' => 'ada@example.com',
                'password' => 'secret123',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['name' => 'Ada Two']);
    }

    public function test_it_rejects_password_shorter_than_eight_characters(): void
    {
        try {
            (new CreateUser)->execute([
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'password' => '1234567',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_rejects_invalid_email(): void
    {
        try {
            (new CreateUser)->execute([
                'name' => 'Ada Lovelace',
                'email' => 'not-an-email',
                'password' => 'secret123',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>}>
     */
    public static function missingRequiredFields(): array
    {
        return [
            'missing name' => ['name', [
                'email' => 'ada@example.com',
                'password' => 'secret123',
            ]],
            'missing email' => ['email', [
                'name' => 'Ada Lovelace',
                'password' => 'secret123',
            ]],
            'missing password' => ['password', [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
            ]],
        ];
    }
}
