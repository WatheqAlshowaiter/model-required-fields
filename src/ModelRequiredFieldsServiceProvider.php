<?php

namespace WatheqAlshowaiter\ModelRequiredFields;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ModelRequiredFieldsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */

        $package
            ->name('model-required-fields');
    }
}
