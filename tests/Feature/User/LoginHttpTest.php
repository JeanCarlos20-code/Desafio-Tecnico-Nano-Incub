<?php

namespace Tests\Feature\User;

use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_login_page_renders_inertia_user_login(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('User/Login'));
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('reservations.index'));
    }

    public function test_valid_login_authenticates_regenerates_session_and_redirects_to_reservations(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'secret123',
        ]);

        $this->get(route('login'));
        $previousSessionId = session()->getId();

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'ada@example.com',
                'password' => 'secret123',
            ])
            ->assertRedirect(route('reservations.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($previousSessionId, session()->getId());
    }

    public function test_login_returns_to_the_intended_protected_url(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'secret123',
        ]);

        $this->get(route('reservations.index'))
            ->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => 'ada@example.com',
            'password' => 'secret123',
        ])
            ->assertRedirect(route('reservations.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_missing_email_returns_field_error_without_authenticating(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'password' => 'secret123',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Informe seu e-mail.']);

        $this->assertGuest();
    }

    public function test_missing_password_returns_field_error_without_authenticating(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'ada@example.com',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['password' => 'Informe sua senha.']);

        $this->assertGuest();
    }

    public function test_malformed_email_returns_field_error_without_authenticating(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'not-an-email',
                'password' => 'secret123',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Informe um endereço de e-mail válido.']);

        $this->assertGuest();
    }

    #[DataProvider('invalidCredentials')]
    public function test_invalid_credentials_return_generic_error_without_authenticating(
        callable $arrange,
        array $payload,
    ): void {
        $arrange();

        $this->from(route('login'))
            ->post(route('login.store'), $payload)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['credentials' => 'E-mail ou senha inválidos.']);

        $this->assertGuest();
    }

    public function test_trimmed_and_lowercased_email_authenticates_the_stored_user(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'secret123',
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => ' Ada@Example.com ',
                'password' => 'secret123',
            ])
            ->assertRedirect(route('reservations.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_failed_login_does_not_flash_or_return_the_raw_password(): void
    {
        $secret = 'super-secret-password';

        $response = $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'ada@example.com',
                'password' => $secret,
            ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('credentials');

        $this->assertArrayNotHasKey('password', session()->get('_old_input', []));
        $this->assertSame('ada@example.com', session()->get('_old_input.email'));
        $this->assertStringNotContainsString($secret, $response->getContent());
        $this->assertGuest();
    }

    public function test_sixth_attempt_is_throttled_even_with_the_correct_password(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'secret123',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'email' => 'ada@example.com',
                    'password' => 'wrong-password',
                ])
                ->assertSessionHasErrors('credentials');
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'ada@example.com',
                'password' => 'secret123',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('credentials');

        $this->assertGuest();
        $this->assertFalse(auth()->check());
        $this->assertTrue($user->is(UserModel::query()->where('email', 'ada@example.com')->first()));
    }

    public function test_unauthenticated_reservations_redirects_to_login(): void
    {
        $this->get(route('reservations.index'))
            ->assertRedirect(route('login'));
    }

    public function test_logout_invalidates_the_session_and_redirects_to_login(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user);
        $this->get(route('reservations.index'))->assertOk();

        $previousSessionId = session()->getId();
        $previousToken = session()->token();

        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotSame($previousSessionId, session()->getId());
        $this->assertNotSame($previousToken, session()->token());
    }

    public function test_login_ignores_extra_name_fields_and_authenticates_on_email_and_password(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'secret123',
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'name' => 'Attacker',
                'email' => 'ada@example.com',
                'password' => 'secret123',
            ])
            ->assertRedirect(route('reservations.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('ada@example.com', $user->fresh()->email);
        $this->assertNotSame('Attacker', $user->fresh()->name);
    }

    /**
     * @return array<string, array{0: callable, 1: array<string, string>}>
     */
    public static function invalidCredentials(): array
    {
        return [
            'unknown email' => [
                fn () => null,
                [
                    'email' => 'missing@example.com',
                    'password' => 'secret123',
                ],
            ],
            'wrong password' => [
                function (): void {
                    UserModel::factory()->create([
                        'email' => 'ada@example.com',
                        'password' => 'secret123',
                    ]);
                },
                [
                    'email' => 'ada@example.com',
                    'password' => 'wrong-password',
                ],
            ],
            'soft-deleted user' => [
                function (): void {
                    $user = UserModel::factory()->create([
                        'email' => 'ada@example.com',
                        'password' => 'secret123',
                    ]);
                    $user->delete();
                },
                [
                    'email' => 'ada@example.com',
                    'password' => 'secret123',
                ],
            ],
        ];
    }
}
