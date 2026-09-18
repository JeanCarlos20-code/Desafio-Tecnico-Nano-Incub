<?php

namespace Tests\Unit\User;

use App\Modules\User\Application\Errors\InvalidCredentials;
use App\Modules\User\Application\UseCases\AuthenticateUser;
use App\Modules\User\Domain\UserAuthenticator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AuthenticateUserTest extends TestCase
{
    public function test_it_completes_without_throwing_when_attempt_succeeds(): void
    {
        $authenticator = new FakeUserAuthenticator(true);

        (new AuthenticateUser($authenticator))->execute('ada@example.com', 'secret123');

        $this->assertSame(1, count($authenticator->attempts));
    }

    public function test_it_throws_invalid_credentials_when_attempt_fails(): void
    {
        $authenticator = new FakeUserAuthenticator(false);

        try {
            (new AuthenticateUser($authenticator))->execute('ada@example.com', 'wrong-password');
            $this->fail('Expected InvalidCredentials to be thrown.');
        } catch (InvalidCredentials) {
            $this->assertSame(
                [['email' => 'ada@example.com', 'password' => 'wrong-password']],
                $authenticator->attempts,
            );
        }
    }

    public function test_it_passes_email_and_password_to_the_authenticator(): void
    {
        $authenticator = new FakeUserAuthenticator(true);

        (new AuthenticateUser($authenticator))->execute('ada@example.com', 'secret123');

        $this->assertSame(
            [['email' => 'ada@example.com', 'password' => 'secret123']],
            $authenticator->attempts,
        );
    }

    public function test_application_and_domain_login_types_do_not_import_illuminate(): void
    {
        foreach ([
            AuthenticateUser::class,
            UserAuthenticator::class,
            InvalidCredentials::class,
        ] as $class) {
            $source = file_get_contents((new ReflectionClass($class))->getFileName());

            $this->assertIsString($source);
            $this->assertStringNotContainsString('Illuminate', $source, $class);
        }
    }
}

final class FakeUserAuthenticator implements UserAuthenticator
{
    /** @var list<array{email: string, password: string}> */
    public array $attempts = [];

    public function __construct(private readonly bool $authenticated) {}

    public function attempt(string $email, string $password): bool
    {
        $this->attempts[] = compact('email', 'password');

        return $this->authenticated;
    }
}
