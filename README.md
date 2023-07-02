[![Stand With Ukraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/banner-direct-single.svg)](https://stand-with-ukraine.pp.ua)

# Laravel Slack Notifier

[![Latest Version on Packagist](https://img.shields.io/packagist/v/stasadev/laravel-slack-notifier.svg?style=flat-square)](https://packagist.org/packages/stasadev/laravel-slack-notifier)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/stasadev/laravel-slack-notifier/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/stasadev/laravel-slack-notifier/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/stasadev/laravel-slack-notifier/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/stasadev/laravel-slack-notifier/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/stasadev/laravel-slack-notifier.svg?style=flat-square)](https://packagist.org/packages/stasadev/laravel-slack-notifier)

Send exceptions and dump variables to Slack.

```php
use Stasadev\SlackNotifier\Facades\SlackNotifier;

SlackNotifier::send(new \RuntimeException('Test exception'));
SlackNotifier::send('Test message');
```

## Installation

Install the package via composer:

```bash
composer require stasadev/laravel-slack-notifier
```

All env variables used by this package (only `LOG_SLACK_WEBHOOK_URL` is required):

```dotenv
APP_NAME=Laravel
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/ABC
LOG_SLACK_CHANNEL=
LOG_SLACK_EMOJI=:boom:
LOG_SLACK_CACHE_SECONDS=0
CACHE_DRIVER=file
```

How to get a webhook URL [in the Slack API docs](https://api.slack.com/messaging/webhooks).

To temporarily disable all logging, simply comment out `LOG_SLACK_WEBHOOK_URL` or set it to an empty string or `null`.

Optionally publish the [config](./config/slack-notifier.php) file with:

```bash
php artisan vendor:publish --tag="slack-notifier"
```

## Usage

To send a message to Slack, simply call `SlackNotifier::send()`.

## Report Exception

```php
// In Laravel 8.x and later
// app/Exceptions/Handler.php
public function register(): void
{
    $this->reportable(function (Throwable $e) {
        \Stasadev\SlackNotifier\Facades\SlackNotifier::send($e);
    });
}

// In Laravel 5.7.x, 5.8.x, 6.x, 7.x
// app/Exceptions/Handler.php
public function report(Throwable $exception)
{
    if ($this->shouldReport($exception) {
        \Stasadev\SlackNotifier\Facades\SlackNotifier::send($exception);
    }

    parent::report($exception);
}
```

## Dump Variable

```php
use Stasadev\SlackNotifier\Facades\SlackNotifier;

$variable = 'message';
// $variable = ['test' => 'array'];
// $variable = new stdClass();

SlackNotifier::send($variable);
```

## Using multiple webhooks

Use an alternative webhook, by specify extra ones in the config file.

```php
// config/slack-notifier.php

'webhook_urls' => [
    'default' => 'https://hooks.slack.com/services/ABC',
    'testing' => 'https://hooks.slack.com/services/DEF',
],
```

The webhook to be used can be chosen using the `to` function.

```php
use Stasadev\SlackNotifier\Facades\SlackNotifier;

SlackNotifier::to('testing')->send('Test message');
```

### Using a custom webhooks

The `to` function also supports custom webhook URLs.

```php
use Stasadev\SlackNotifier\Facades\SlackNotifier;

SlackNotifier::to('https://custom-url.com')->send('Test message');
```

## Sending message to another channel

You can send a message to a channel (use `LOG_SLACK_CHANNEL`) other than the default one for the webhook, by passing it to the `channel` function.

```php
use Stasadev\SlackNotifier\Facades\SlackNotifier;

SlackNotifier::channel('reminders')->send('Test message');
```

## Slack bot customizing

Use `username` (use `APP_NAME`) and `emoji` (use `LOG_SLACK_EMOJI`) to make your messages unique, or override them right before sending.

```php
use Stasadev\SlackNotifier\Facades\SlackNotifier;

SlackNotifier::username('My Laravel Bot')->emoji(':tada:')->send('Test message');
```

### Formatting

Extend the default `Stasadev\SlackNotifier\SlackNotifierFormatter::class` to format the messages however you like. Then simply replace the `formatter` key in the configuration file.

```php
// config/slack-notifier.php

'formatter' => App\Formatters\CustomSlackNotifierFormatter::class,
```

### Additional context in the message

Include additional `context` in a Slack message (use `dont_flash` to exclude sensitive info from `context`). It will be added as an attachment.

### Exception stack trace filtering

Stack traces for exceptions in Laravel usually contain many lines, including framework files. Usually, you are only interested in tracking exception details in the application files.
You can filter it out with the `dont_trace` config option.

### Caching the same exceptions

Sometimes a large group of exceptions is thrown, and you don't want to log each of them because they are the same.

Use `LOG_SLACK_CACHE_SECONDS` (uses Laravel `CACHE_DRIVER` under the hood) to suppress output for X seconds, or pass it to the `cacheSeconds` function.

```php
use Stasadev\SlackNotifier\Facades\SlackNotifier;

SlackNotifier::cacheSeconds(60)->send(new \RuntimeException('Test exception'));
```

## Testing

```bash
composer test
```

## Credits

Inspired by [spatie/laravel-slack-alerts](https://github.com/spatie/laravel-slack-alerts).

- [Stanislav Zhuk](https://github.com/stasadev)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
