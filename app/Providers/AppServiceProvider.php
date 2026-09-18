<?php

namespace App\Providers;

use App\Modules\Room\Domain\Repositories\RoomRepository;
use App\Modules\Room\Infra\Database\Repositories\EloquentRoomRepository;
use App\Modules\User\Domain\Repositories\UserRepository;
use App\Modules\User\Domain\UserAuthenticator;
use App\Modules\User\Infra\Database\Repositories\EloquentUserRepository;
use App\Modules\User\Infra\Http\LaravelUserAuthenticator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(UserAuthenticator::class, LaravelUserAuthenticator::class);
        $this->app->bind(RoomRepository::class, EloquentRoomRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config([
            'inertia.pages.paths' => [resource_path('js/Pages')],
        ]);
    }
}
