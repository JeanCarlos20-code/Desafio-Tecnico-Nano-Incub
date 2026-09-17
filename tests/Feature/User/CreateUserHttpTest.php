<?php

namespace Tests\Feature\User;

use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateUserHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_register_page_renders_inertia_user_create(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('User/Create'));
    }

    public function test_create_page_renders_inertia_user_create(): void
    {
        $this->get(route('users.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('User/Create'));
    }

    public function test_register_persists_authenticates_and_redirects_to_reservations(): void
    {
        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'password' => 'secret123',
            ])
            ->assertRedirect(route('reservations.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);

        $this->get(route('reservations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Reservation/Index'));
    }

    public function test_store_persists_user_and_redirects(): void
    {
        $this->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'password' => 'secret123',
            ])
            ->assertRedirect(route('reservations.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);
    }

    #[DataProvider('missingRequiredFields')]
    public function test_store_rejects_missing_required_fields(string $field, array $payload, string $message): void
    {
        $this->from(route('users.create'))
            ->post(route('users.store'), $payload)
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        UserModel::factory()->create([
            'email' => 'ada@example.com',
        ]);

        $this->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Ada Two',
                'email' => 'ada@example.com',
                'password' => 'secret123',
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors(['email' => 'Este e-mail já está cadastrado.']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['name' => 'Ada Two']);
    }

    public function test_store_rejects_email_of_soft_deleted_user(): void
    {
        $existing = UserModel::factory()->create([
            'email' => 'ada@example.com',
        ]);
        $existing->delete();

        $this->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Ada Two',
                'email' => 'ada@example.com',
                'password' => 'secret123',
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors(['email' => 'Este e-mail já está cadastrado.']);

        $this->assertSame(1, UserModel::withTrashed()->count());
        $this->assertDatabaseMissing('users', ['name' => 'Ada Two']);
    }

    public function test_store_rejects_password_shorter_than_eight_characters(): void
    {
        $this->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'password' => '1234567',
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors(['password' => 'A senha deve possuir pelo menos 8 caracteres.']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_store_rejects_invalid_email(): void
    {
        $this->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Ada Lovelace',
                'email' => 'not-an-email',
                'password' => 'secret123',
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors(['email' => 'Informe um endereço de e-mail válido.']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_store_accepts_eight_character_password(): void
    {
        $this->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Grace Hopper',
                'email' => 'grace@example.com',
                'password' => '12345678',
            ])
            ->assertRedirect(route('reservations.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_store_ignores_email_verified_at(): void
    {
        $this->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'password' => 'secret123',
                'email_verified_at' => '2026-01-01 00:00:00',
            ])
            ->assertRedirect(route('reservations.index'));

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);
    }

    public function test_register_persists_email_trimmed_and_lowercased(): void
    {
        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Ada Lovelace',
                'email' => ' Ada@Example.com ',
                'password' => 'secret123',
            ])
            ->assertRedirect(route('reservations.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'ada@example.com',
        ]);
    }

    public function test_store_does_not_flash_or_return_the_raw_password(): void
    {
        $secret = 'super-secret-password';

        $response = $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Ada Lovelace',
                'email' => 'not-an-email',
                'password' => $secret,
            ]);

        $response
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('email');

        $this->assertArrayNotHasKey('password', session()->get('_old_input', []));
        $this->assertStringNotContainsString($secret, $response->getContent());
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>, 2: string}>
     */
    public static function missingRequiredFields(): array
    {
        return [
            'missing name' => ['name', [
                'email' => 'ada@example.com',
                'password' => 'secret123',
            ], 'Informe seu nome.'],
            'missing email' => ['email', [
                'name' => 'Ada Lovelace',
                'password' => 'secret123',
            ], 'Informe um endereço de e-mail válido.'],
            'missing password' => ['password', [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
            ], 'A senha deve possuir pelo menos 8 caracteres.'],
        ];
    }
}
