<?php

namespace Stasadev\SlackNotifier\Tests;

use Illuminate\Support\Facades\Notification;
use Orchestra\Testbench\TestCase as Orchestra;
use Stasadev\SlackNotifier\Notifications\SendToSlack;
use Stasadev\SlackNotifier\SlackNotifierServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function getPackageProviders($app): array
    {
        return [
            SlackNotifierServiceProvider::class,
        ];
    }

    protected function assertSlackNotificationsSent(int $count): void
    {
        $fake = Notification::getFacadeRoot();

        // Laravel 8 renamed assertTimesSent($count, $class) to assertSentTimes($class, $count).
        if (method_exists($fake, 'assertSentTimes')) {
            $fake->assertSentTimes(SendToSlack::class, $count);
        } else {
            $fake->assertTimesSent($count, SendToSlack::class);
        }
    }
}
