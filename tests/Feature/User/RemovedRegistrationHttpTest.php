<?php

namespace Tests\Feature\User;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RemovedRegistrationHttpTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('removedRegistrationPaths')]
    public function test_removed_registration_paths_return_404_and_do_not_insert_a_users_row(
        string $method,
        string $uri,
    ): void {
        $this->assertDatabaseCount('users', 3);

        $this->{$method}($uri, [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'Senha1234',
        ])->assertNotFound();

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    public function test_removed_registration_named_routes_are_unavailable(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('register.store'));
        $this->assertFalse(Route::has('users.create'));
        $this->assertFalse(Route::has('users.store'));
        $this->assertFalse(Route::has('password.request'));
        $this->assertFalse(Route::has('verification.notice'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function removedRegistrationPaths(): array
    {
        return [
            'GET /register' => ['get', '/register'],
            'POST /register' => ['post', '/register'],
            'GET /users/create' => ['get', '/users/create'],
            'POST /users' => ['post', '/users'],
            'GET /forgot-password' => ['get', '/forgot-password'],
            'POST /forgot-password' => ['post', '/forgot-password'],
            'GET /reset-password' => ['get', '/reset-password'],
            'POST /reset-password' => ['post', '/reset-password'],
            'GET /email/verification-notification' => ['get', '/email/verification-notification'],
            'POST /email/verification-notification' => ['post', '/email/verification-notification'],
        ];
    }
}
