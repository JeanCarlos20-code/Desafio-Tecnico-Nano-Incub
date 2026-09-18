<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
