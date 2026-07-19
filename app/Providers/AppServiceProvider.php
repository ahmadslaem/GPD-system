<?php

namespace App\Providers;

use App\Models\Family;
use App\Policies\FamilyPolicy;

use Illuminate\Support\Facades\Gate ;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

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
       Gate::policy(
        Family::class,
        FamilyPolicy::class
    );
    }
}
