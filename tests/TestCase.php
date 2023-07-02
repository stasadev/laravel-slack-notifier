<?php

namespace Stasadev\SlackNotifier\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Stasadev\SlackNotifier\SlackNotifierServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SlackNotifierServiceProvider::class,
        ];
    }
}
