<?php

namespace Tests\Feature\Database;

use App\Modules\User\Infra\Database\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UsersMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_database_applies_adr_002_users_columns_from_migrations(): void
    {
        $expected = ['created_at', 'deleted_at', 'email', 'id', 'name', 'password', 'remember_token', 'updated_at'];
        $actual = Schema::getColumnListing('users');
        sort($actual);

        $this->assertSame($expected, $actual);

        foreach ($expected as $column) {
            $this->assertTrue(Schema::hasColumn('users', $column), "Missing column: {$column}");
        }

        $this->assertFalse(Schema::hasColumn('users', 'email_verified_at'));
    }

    public function test_refresh_database_persists_the_three_default_administrators(): void
    {
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseHas('users', [
            'name' => 'Gertrudes',
            'email' => 'teste@mail.com',
        ]);
        $this->assertDatabaseHas('users', [
            'name' => 'Marcelo',
            'email' => 'teste2@mail.com',
        ]);
        $this->assertDatabaseHas('users', [
            'name' => 'Emerson',
            'email' => 'teste3@mail.com',
        ]);
    }

    public function test_default_administrator_passwords_are_argon2i_hashes_of_senha123(): void
    {
        foreach (['teste@mail.com', 'teste2@mail.com', 'teste3@mail.com'] as $email) {
            $hash = DB::table('users')->where('email', $email)->value('password');

            $this->assertIsString($hash);
            $this->assertNotSame('Senha123', $hash);
            $this->assertTrue(Hash::check('Senha123', $hash));
            $this->assertSame('argon2i', password_get_info($hash)['algoName']);
        }
    }

    public function test_database_seeder_does_not_insert_additional_administrators(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_rolling_back_the_default_administrators_migration_deletes_only_those_emails(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
        ]);

        $this->assertDatabaseCount('users', 4);

        $this->artisan('migrate:rollback', [
            '--path' => 'database/migrations/2026_09_19_000000_insert_default_administrators.php',
        ])->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'teste@mail.com']);
        $this->assertDatabaseMissing('users', ['email' => 'teste2@mail.com']);
        $this->assertDatabaseMissing('users', ['email' => 'teste3@mail.com']);
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
        $this->assertDatabaseCount('users', 1);
    }
}
