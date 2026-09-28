<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() === 'sqlsrv') {
                $event->connection->unprepared('SET DATEFORMAT ymd');
            }
        });
        Gate::before(fn (User $user) => $user->hasRole('system-admin') ? true : null);
        Gate::before(fn (User $user) => $user->hasRole('system-admin') ? true : null);
    }
}
