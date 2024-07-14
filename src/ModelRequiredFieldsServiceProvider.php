<?php

namespace WatheqAlshowaiter\ModelRequiredFields;

use Illuminate\Support\ServiceProvider;

class ModelRequiredFieldsServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
