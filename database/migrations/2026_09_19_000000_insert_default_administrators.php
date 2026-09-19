<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $password = Hash::make('Senha123');

        DB::table('users')->insert([
            [
                'id' => (string) Str::uuid7(),
                'name' => 'Gertrudes',
                'email' => 'teste@mail.com',
                'password' => $password,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'id' => (string) Str::uuid7(),
                'name' => 'Marcelo',
                'email' => 'teste2@mail.com',
                'password' => $password,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'id' => (string) Str::uuid7(),
                'name' => 'Emerson',
                'email' => 'teste3@mail.com',
                'password' => $password,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->whereIn('email', [
            'teste@mail.com',
            'teste2@mail.com',
            'teste3@mail.com',
        ])->delete();
    }
};
