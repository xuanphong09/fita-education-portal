<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
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

        if (env('APP_ENV') !== 'local') {
            URL::forceScheme('https');
        }

        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return route('password.setup', [
                'token' => $token,
                'email' => $user->email,
            ]);
        });
    }
}
