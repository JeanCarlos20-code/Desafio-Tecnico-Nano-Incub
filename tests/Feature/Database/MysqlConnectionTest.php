<?php

namespace Tests\Feature\Database;

use App\Modules\User\Infra\Database\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MysqlConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_phpunit_uses_mysql_painel_administrativo_test(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('mysql', config('database.connections.'.config('database.default').'.driver'));
        $this->assertSame('painel_administrativo_test', config('database.connections.mysql.database'));
        $this->assertNotSame('sqlite', config('database.default'));
        $this->assertNotSame('painel_administrativo', config('database.connections.mysql.database'));
        $this->assertNotSame(':memory:', config('database.connections.mysql.database'));
        $this->assertNotSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame('painel_administrativo_test', DB::connection()->getDatabaseName());
    }

    public function test_refresh_database_persists_a_row_on_the_test_database(): void
    {
        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret123',
        ]);

        $this->assertSame('painel_administrativo_test', DB::connection()->getDatabaseName());
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);
    }
}
