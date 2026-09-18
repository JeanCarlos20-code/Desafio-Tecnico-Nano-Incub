<?php

namespace App\Modules\User\Infra\Database\Repositories;

use App\Modules\User\Domain\Entities\User;
use App\Modules\User\Domain\Repositories\UserRepository;
use App\Modules\User\Infra\Database\Models\User as UserModel;
use DateTimeImmutable;

final class EloquentUserRepository implements UserRepository
{
    public function existsByEmail(string $email): bool
    {
        return UserModel::query()->withTrashed()->where('email', $email)->exists();
    }

    public function create(string $name, string $email, string $password): User
    {
        $model = UserModel::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        return $this->toDomain($model->refresh());
    }

    private function toDomain(UserModel $model): User
    {
        return new User(
            id: (string) $model->getKey(),
            name: $model->name,
            email: $model->email,
            passwordHash: $model->getAuthPassword(),
            rememberToken: $model->getAttributes()['remember_token'] ?? null,
            createdAt: self::immutable($model->created_at),
            updatedAt: self::immutable($model->updated_at),
            deletedAt: self::immutable($model->deleted_at),
        );
    }

    private static function immutable(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($value);
    }
}
