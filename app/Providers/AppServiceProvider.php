<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Super Admin bypass — vượt qua mọi permission check
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
//        if (str_contains(request()->getHost(), 'ngrok-free.app')) {
            URL::forceScheme('https');
//        }
    }
}
