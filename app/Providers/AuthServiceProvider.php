<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Ejemplo: 'App\Models\Novel' => 'App\Policies\NovelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Puedes definir gates personalizados aquí
        // Ejemplo:
        // Gate::define('update-novel', function ($user, $novel) {
        //     return $user->id === $novel->user_id;
        // });
    }
}