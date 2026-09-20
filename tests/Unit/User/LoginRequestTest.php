<?php

namespace Tests\Unit\User;

use App\Modules\User\Infra\Http\Requests\LoginRequest;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoginRequestTest extends TestCase
{
    public function test_it_rejects_a_missing_email_with_informe_seu_e_mail(): void
    {
        $errors = $this->validationErrors([
            'password' => 'Senha123',
        ]);

        $this->assertSame(['Informe seu e-mail.'], $errors['email']);
    }

    public function test_it_rejects_a_missing_password_with_informe_sua_senha(): void
    {
        $errors = $this->validationErrors([
            'email' => 'ada@example.com',
        ]);

        $this->assertSame(['Informe sua senha.'], $errors['password']);
    }

    public function test_it_rejects_a_malformed_email_with_informe_um_endereco_de_e_mail_valido(): void
    {
        $errors = $this->validationErrors([
            'email' => 'not-an-email',
            'password' => 'Senha123',
        ]);

        $this->assertSame(['Informe um endereço de e-mail válido.'], $errors['email']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, list<string>>
     */
    private function validationErrors(array $payload): array
    {
        $request = LoginRequest::create('/login', 'POST', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        try {
            $request->validateResolved();
            $this->fail('Expected validation to fail');
        } catch (ValidationException $exception) {
            return $exception->errors();
        }
    }
}
