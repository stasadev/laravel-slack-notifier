[![Latest Version on Packagist](https://img.shields.io/packagist/v/stasadev/laravel-slack-notifier.svg?style=flat-square)](https://packagist.org/packages/stasadev/laravel-slack-notifier)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/stasadev/laravel-slack-notifier/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/stasadev/laravel-slack-notifier/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/stasadev/laravel-slack-notifier/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/stasadev/laravel-slack-notifier/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/stasadev/laravel-slack-notifier.svg?style=flat-square)](https://packagist.org/packages/stasadev/laravel-slack-notifier)

# Laravel Slack Notifier

Send exceptions and dump variables to Slack.

```php
use Stasadev\SlackNotifier\Facades\SlackNotifier;

SlackNotifier::send(new \RuntimeException('Test exception'));
SlackNotifier::send('Test message');
```

## Requirements

- PHP 7.1.3 or later
- Laravel 5.7 to 13

## Installation

Install the package via composer:

```bash
composer require stasadev/laravel-slack-notifier
```

[Create an incoming webhook](https://api.slack.com/messaging/webhooks) in Slack and add its URL to `.env`:

```dotenv
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/ABC
```

To stop sending messages, remove `LOG_SLACK_WEBHOOK_URL` or leave it empty.

Laravel's own `slack` log channel reads the same variable. If you also log to that channel, every exception reaches Slack twice.

All env variables used by this package:

| Variable                  | Default                     | Description                                                                         |
|---------------------------|-----------------------------|-------------------------------------------------------------------------------------|
| `LOG_SLACK_WEBHOOK_URL`   |                             | The webhook URL. Required.                                                          |
| `LOG_SLACK_CHANNEL`       | The channel of the webhook  | The channel to post to, see [channel, username and emoji](#channel-username-and-emoji). |
| `APP_NAME`                | `Laravel Log`               | The name of the Slack bot.                                                          |
| `LOG_SLACK_EMOJI`         | `:boom:`                    | The icon of the Slack bot.                                                          |
| `LOG_SLACK_CACHE_SECONDS` | `0`                         | Seconds to [suppress the same exception](#suppress-repeated-exceptions) for.        |

Optionally publish the [config](./config/slack-notifier.php) file with:

```bash
php artisan vendor:publish --tag="slack-notifier"
```

## Usage

To send a message to Slack, simply call `SlackNotifier::send()`.

An exception is sent as an error with its stack trace and previous exceptions. Any other value is sent as a success message.

### Report exceptions

Send every reported exception to Slack from the exception handler. Laravel still logs it as usual.

```php
// bootstrap/app.php (Laravel 11 and later)

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Stasadev\SlackNotifier\Facades\SlackNotifier;

return Application::configure(basePath: dirname(__DIR__))
    // ...
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $e) {
            SlackNotifier::send($e);
        });
    })->create();
```

<details>
<summary>Laravel 5.7 to 10</summary>

```php
// app/Exceptions/Handler.php (Laravel 8, 9, 10)

use Stasadev\SlackNotifier\Facades\SlackNotifier;

public function register(): void
{
    $this->reportable(function (Throwable $e) {
        SlackNotifier::send($e);
    });
}
```

```php
// app/Exceptions/Handler.php (Laravel 7)

use Stasadev\SlackNotifier\Facades\SlackNotifier;

public function report(Throwable $exception)
{
    if ($this->shouldReport($exception)) {
        SlackNotifier::send($exception);
    }

    parent::report($exception);
}
```

```php
// app/Exceptions/Handler.php (Laravel 5.7, 5.8, 6)

use Stasadev\SlackNotifier\Facades\SlackNotifier;

public function report(Exception $exception)
{
    if ($this->shouldReport($exception)) {
        SlackNotifier::send($exception);
    }

    parent::report($exception);
}
```

</details>

### Dump variables

```php
use Stasadev\SlackNotifier\Facades\SlackNotifier;

SlackNotifier::send('message');
SlackNotifier::send(['key' => 'value']);
SlackNotifier::send(new stdClass());
```

Arrays and objects are converted to text with Monolog's `NormalizerFormatter`, like the context of a log message.

### Choose a webhook

Add more webhooks to the config file:

```php
// config/slack-notifier.php

'webhook_urls' => [
    'default' => env('LOG_SLACK_WEBHOOK_URL'),
    'testing' => 'https://hooks.slack.com/services/DEF',
],
```

Choose one by its name with `to()`, or pass a webhook URL:

```php
use Stasadev\SlackNotifier\Facades\SlackNotifier;

SlackNotifier::to('testing')->send('Test message');
SlackNotifier::to('https://hooks.slack.com/services/GHI')->send('Test message');
```

A name that is missing from `webhook_urls` reports `WebhookUrlDoesNotExist`, while a name with an empty URL disables sending.

### Channel, username and emoji

Override the channel (`LOG_SLACK_CHANNEL`), the bot name (`APP_NAME`) and the bot icon (`LOG_SLACK_EMOJI`) for one message:

```php
use Stasadev\SlackNotifier\Facades\SlackNotifier;

SlackNotifier::channel('#reminders')
    ->username('My Laravel Bot')
    ->emoji(':tada:')
    ->send('Test message');
```

> [!NOTE]
> Webhooks of Slack apps always post to the channel chosen when the app was installed, with the name and icon of the app, and ignore these options.

### Suppress repeated exceptions

When the same exception is thrown many times in a row, send it only once in a given number of seconds with `LOG_SLACK_CACHE_SECONDS`, or with `cacheSeconds()` for one message:

```php
use Stasadev\SlackNotifier\Facades\SlackNotifier;

SlackNotifier::cacheSeconds(60)->send(new \RuntimeException('Test exception'));
```

Exceptions are the same when they have the same class, message, file, line, stack trace and previous exceptions. The package stores a key for each exception in the default Laravel cache store. Other values are never suppressed.

## Configuration

### Context

Each message gets a "Context" attachment with the data listed in the `context` config option:

| Item      | Data                                                                    |
|-----------|-------------------------------------------------------------------------|
| `get`     | Query string (default)                                                  |
| `post`    | Request body (default)                                                  |
| `request` | Query string and request body                                           |
| `headers` | Request headers                                                         |
| `files`   | Uploaded files                                                          |
| `cookie`  | Cookies (default)                                                       |
| `session` | Session data (default)                                                  |
| `server`  | Server and execution environment                                        |
| `context` | Data of the Laravel [Context](https://laravel.com/docs/context) facade, Laravel 11+ |

Request data is not included in the console. Data of the Context facade is included in the console too, and hidden Context data is never included.

Keys listed in `dont_flash` are removed from each item. Only top-level keys are removed, and header names are in lower case.

> [!WARNING]
> Everyone who can read the Slack channel can read the context. Decrypted cookies include the session ID and the "remember me" token, which are enough to log in as the user. Headers include the `Authorization` header, and server data can include environment variables, such as database passwords. Keep only the items you need in `context`, and add sensitive keys to `dont_flash`.

### Stack trace

Stack traces usually contain many lines from the framework, while you are only interested in the lines from your application. Lines that contain any of the strings in `dont_trace` are replaced with `...`:

```php
// config/slack-notifier.php

'dont_trace' => [
    '/vendor/symfony/',
    '/vendor/laravel/framework/',
    '/vendor/barryvdh/laravel-debugbar/',
],
```

### Formatting

Extend `Stasadev\SlackNotifier\SlackNotifierFormatter` to change the messages, and set your class in the `formatter` config option:

```php
// config/slack-notifier.php

'formatter' => App\Slack\SlackFormatter::class,
```

```php
namespace App\Slack;

use Illuminate\Notifications\Messages\SlackMessage;
use Stasadev\SlackNotifier\Notifications\SendToSlack;
use Stasadev\SlackNotifier\SlackNotifierFormatter;

class SlackFormatter extends SlackNotifierFormatter
{
    public function format(SendToSlack $notification): SlackMessage
    {
        return parent::format($notification)->content(config('app.url'));
    }
}
```

`format()` builds the whole message. To change one part of it, override `formatException()`, `formatVariable()`, `getPretext()`, `normalizeTrace()` or `getContext()` instead.

## When sending fails

Messages are sent right away, not on a queue, so a slow response from Slack also slows down the request that sends the message.

`SlackNotifier::send()` never throws, as it usually runs while Laravel reports an exception, and an exception thrown there would stop Laravel from logging the original one. Errors are passed to Laravel's `report()` instead, and Laravel logs them as usual:

- An invalid config, such as an unknown webhook name, an invalid webhook URL or a missing formatter class, reports the matching exception.
- If Slack cannot be reached or rejects the message, or the cache store fails, the error is reported as `WebhookSendFail`.

The package never sends its own exceptions to Slack, so a failing webhook cannot cause a loop.

## Testing your application

Disable sending in your tests in `phpunit.xml`:

```xml
<env name="LOG_SLACK_WEBHOOK_URL" value=""/>
```

Or mock the facade to check what is sent:

```php
use Stasadev\SlackNotifier\Facades\SlackNotifier;

SlackNotifier::shouldReceive('send')->once()->with('Test message');
```

## Testing

```bash
composer test
```

## Changelog

See [GitHub Releases](https://github.com/stasadev/laravel-slack-notifier/releases) for what has changed in each version.

## Credits

Inspired by [spatie/laravel-slack-alerts](https://github.com/spatie/laravel-slack-alerts).

- [Stanislav Zhuk](https://github.com/stasadev)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
