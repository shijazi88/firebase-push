<?php
namespace Hijazi\FirebasePush;

use Illuminate\Support\ServiceProvider;

class FirebasePushServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Merge package configuration with application's published configuration
        $this->mergeConfigFrom(__DIR__.'/../config/firebase_push.php', 'firebase_push');

        // Register the service with the server key from the configuration
        $this->app->singleton(FirebasePushService::class, function ($app) {
            return new FirebasePushService(config('firebase_push.server_key'));
        });
    }

    public function boot()
    {
        // Publish the configuration file
        $this->publishes([
            __DIR__.'/../config/firebase_push.php' => config_path('firebase_push.php'),
        ], 'firebase-push-config');
    }
}
