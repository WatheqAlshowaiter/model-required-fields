<?php

namespace WatheqAlshowaiter\ModelRequiredFields\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use WatheqAlshowaiter\ModelRequiredFields\ModelRequiredFieldsServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            ModelRequiredFieldsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}
