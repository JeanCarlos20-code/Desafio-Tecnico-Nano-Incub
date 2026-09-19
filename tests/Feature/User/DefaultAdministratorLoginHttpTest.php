<?php

namespace Tests\Feature\User;

use App\Modules\User\Infra\Database\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DefaultAdministratorLoginHttpTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('defaultAdministratorAccounts')]
    public function test_default_administrator_can_post_login_and_is_redirected_to_reservations(
        string $email,
    ): void {
        $user = UserModel::query()->where('email', $email)->firstOrFail();

        $this->from('/login')
            ->post('/login', [
                'email' => $email,
                'password' => 'Senha123',
            ])
            ->assertRedirect('/reservations');

        $this->assertAuthenticatedAs($user);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function defaultAdministratorAccounts(): array
    {
        return [
            'Gertrudes' => ['teste@mail.com'],
            'Marcelo' => ['teste2@mail.com'],
            'Emerson' => ['teste3@mail.com'],
        ];
    }
}
