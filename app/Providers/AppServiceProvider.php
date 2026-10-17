<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\FirestoreDocumentStore::class);
        $this->app->alias(\App\Services\FirestoreDocumentStore::class, \App\Contracts\DocumentStore::class);
        $this->app->scoped(\App\Support\CloudData::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Auth::provider('firestore', fn () => new \App\Auth\DocumentUserProvider);
        \Illuminate\Support\Facades\Cache::extend('firestore', fn ($app) => \Illuminate\Support\Facades\Cache::repository(
            new \App\Services\FirestoreCacheStore($app->make(\App\Services\FirestoreDocumentStore::class), (string) config('cache.prefix'))
        ));
        \Illuminate\Support\Facades\Session::extend('firestore', fn ($app) => new \App\Services\FirestoreSessionHandler(
            $app->make(\App\Services\FirestoreDocumentStore::class), (int) config('session.lifetime'), (string) config('session.cookie')
        ));
    }
}
