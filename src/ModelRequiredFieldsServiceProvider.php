<?php

namespace WatheqAlshowaiter\ModelRequiredFields;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Arr;




class ModelRequiredFieldsServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
