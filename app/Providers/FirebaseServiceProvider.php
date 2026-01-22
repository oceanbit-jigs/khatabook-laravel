<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

class FirebaseServiceProvider extends ServiceProvider
{
    public function register()
    {
         // Bind Guzzle Client
         $this->app->bind(ClientInterface::class, function () {
            return new Client();
        });

        $this->app->singleton(Messaging::class, function ($app) {
            $firebase = (new Factory)
                ->withServiceAccount(storage_path('app/service-account.json'));

            return $firebase->createMessaging();
        });
    }

    public function boot()
    {
        //
    }
}
