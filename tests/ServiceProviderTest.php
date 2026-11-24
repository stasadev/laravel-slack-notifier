<?php

namespace Stasadev\SlackNotifier\Tests;

use Illuminate\Support\ServiceProvider;
use Stasadev\SlackNotifier\SlackNotifierFormatter;
use Stasadev\SlackNotifier\SlackNotifierServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_it_merges_the_package_config(): void
    {
        $this->assertSame(SlackNotifierFormatter::class, config('slack-notifier.formatter'));
        $this->assertSame(['get', 'post', 'cookie', 'session'], config('slack-notifier.context'));
        $this->assertSame(['current_password', 'password', 'password_confirmation'], config('slack-notifier.dont_flash'));
        $this->assertSame(':boom:', config('slack-notifier.emoji'));
    }

    public function test_it_publishes_the_config_file(): void
    {
        $paths = ServiceProvider::pathsToPublish(SlackNotifierServiceProvider::class, 'slack-notifier');

        $this->assertCount(1, $paths);
        $this->assertSame(realpath(__DIR__.'/../config/slack-notifier.php'), realpath((string) key($paths)));
        $this->assertSame(config_path('slack-notifier.php'), current($paths));
    }
}
