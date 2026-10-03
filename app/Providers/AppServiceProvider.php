<?php

namespace App\Providers;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Observers\AuditObserver;
use App\Policies\FamilyPolicy;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate ;
use Illuminate\Support\Facades\RateLimiter;
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

        // سجل التدقيق: توثيق تلقائي لكل إنشاء/تعديل/حذف (FR-SYS-06)
        Family::observe(AuditObserver::class);
        FamilyMember::observe(AuditObserver::class);

        // حماية تسجيل الدخول من الهجمات العنيفة: 5 محاولات لكل دقيقة لكل (إيميل + IP)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->string('email')->lower()->toString().'|'.$request->ip());
        });
    }
}
