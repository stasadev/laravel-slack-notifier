<?php

namespace Stasadev\SlackNotifier;

use Illuminate\Support\ServiceProvider;

class SlackNotifierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishConfigs();
        }
    }

    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/slack-notifier.php', 'slack-notifier');
    }

    protected function publishConfigs(): void
    {
        $this->publishes([
            __DIR__.'/../config/slack-notifier.php' => config_path('slack-notifier.php'),
        ], 'slack-notifier');
    }
}
