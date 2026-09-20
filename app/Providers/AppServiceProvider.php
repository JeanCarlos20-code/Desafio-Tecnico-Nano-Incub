<?php

namespace App\Providers;

use App\Modules\Reservation\Application\Transaction;
use App\Modules\Reservation\Domain\Clock;
use App\Modules\Reservation\Domain\OccupancyRoomCatalog;
use App\Modules\Reservation\Domain\Repositories\ReservationRepository;
use App\Modules\Reservation\Infra\Database\Repositories\EloquentOccupancyRoomCatalog;
use App\Modules\Reservation\Infra\Database\Repositories\EloquentReservationRepository;
use App\Modules\Reservation\Infra\Persistence\LaravelTransaction;
use App\Modules\Reservation\Infra\Time\LaravelClock;
use App\Modules\Room\Domain\Repositories\RoomRepository;
use App\Modules\Room\Infra\Database\Repositories\EloquentRoomRepository;
use App\Modules\User\Domain\Repositories\UserRepository;
use App\Modules\User\Infra\Database\Repositories\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(RoomRepository::class, EloquentRoomRepository::class);
        $this->app->bind(ReservationRepository::class, EloquentReservationRepository::class);
        $this->app->bind(OccupancyRoomCatalog::class, EloquentOccupancyRoomCatalog::class);
        $this->app->bind(Clock::class, LaravelClock::class);
        $this->app->bind(Transaction::class, LaravelTransaction::class);
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
