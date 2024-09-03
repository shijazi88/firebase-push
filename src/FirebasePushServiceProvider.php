<?php
namespace Hijazi\FirebasePush;

use Illuminate\Support\ServiceProvider;

class FirebasePushServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/firebase_push.php', 'firebase_push');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/firebase_push.php' => config_path('firebase_push.php'),
        ], 'firebase-push-config');
    }
}
