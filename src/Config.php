<?php

namespace Stasadev\SlackNotifier;

use Stasadev\SlackNotifier\Exceptions\FormatterClassDoesNotExist;
use Stasadev\SlackNotifier\Exceptions\WebhookUrlDoesNotExist;
use Stasadev\SlackNotifier\Exceptions\WebhookUrlNotValid;

class Config
{
    public static function getFormatter(array $arguments = []): SlackNotifierFormatter
    {
        $formatterClass = config('slack-notifier.formatter');

        if (is_null($formatterClass) || ! class_exists($formatterClass)) {
            throw FormatterClassDoesNotExist::make($formatterClass);
        }

        return app($formatterClass, $arguments);
    }

    public static function getWebhookUrl(string $name): ?string
    {
        if (filter_var($name, FILTER_VALIDATE_URL)) {
            return $name;
        }

        $key = "slack-notifier.webhook_urls.{$name}";

        // An empty webhook URL disables sending, but a missing one is most likely a typo.
        if (! config()->has($key)) {
            throw WebhookUrlDoesNotExist::make($name);
        }

        $url = config($key);

        if (! $url) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw WebhookUrlNotValid::make($name, $url);
        }

        return $url;
    }
}
