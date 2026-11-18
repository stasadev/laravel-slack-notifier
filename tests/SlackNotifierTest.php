<?php

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Notification;
use Stasadev\SlackNotifier\Exceptions\FormatterClassDoesNotExist;
use Stasadev\SlackNotifier\Exceptions\WebhookSendFail;
use Stasadev\SlackNotifier\Exceptions\WebhookUrlNotValid;
use Stasadev\SlackNotifier\Facades\SlackNotifier;
use Stasadev\SlackNotifier\Notifications\SendToSlack;

beforeEach(function () {
    Notification::fake();

    if (class_exists(Context::class)) {
        Context::flush();
    }
});

it('can send a notification with an exception to slack using the default webhook url', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

    SlackNotifier::send(new RuntimeException('test-exception'));

    Notification::assertSentTimes(SendToSlack::class, 1);
});

it('cannot send a notification with its fail to slack using the default webhook url', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

    SlackNotifier::send(WebhookSendFail::make(new RuntimeException('webhook is failed')));

    Notification::assertNothingSent();
});

it('can send a notification with a message to slack using the default webhook url', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

    SlackNotifier::send('test-data');

    Notification::assertSentTimes(SendToSlack::class, 1);
});

it('can send a notification with an array to slack using the default webhook url', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

    SlackNotifier::send([
        'test-key' => 'test-data',
    ]);

    Notification::assertSentTimes(SendToSlack::class, 1);
});

it('can send a notification with an object to slack using the default webhook url', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

    SlackNotifier::send(new stdClass);

    Notification::assertSentTimes(SendToSlack::class, 1);
});

it('can send a notification with null to slack using the default webhook url', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

    SlackNotifier::send(null);

    Notification::assertSentTimes(SendToSlack::class, 1);
});

it('can send a notification with boolean to slack using the default webhook url', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

    SlackNotifier::send(true);

    Notification::assertSentTimes(SendToSlack::class, 1);
});

it('can send a notification with a message to slack using an alternative webhook url', function () {
    config()->set('slack-notifier.webhook_urls.testing', 'https://default-domain.com');

    SlackNotifier::to('testing')->send('test-data');

    Notification::assertSentTimes(SendToSlack::class, 1);
});

it('can send a notification with a message to slack alternative channel', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

    SlackNotifier::channel('random')->send('test-data');

    Notification::assertSentTimes(SendToSlack::class, 1);
});

it('will throw an exception for a non-existing formatter class', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');
    config()->set('slack-notifier.formatter', 'non-existing-job');

    SlackNotifier::send('test-data');
})->throws(FormatterClassDoesNotExist::class);

it('will not throw an exception for an empty webhook url', function () {
    config()->set('slack-notifier.webhook_urls.default', '');

    SlackNotifier::send('test-data');
})->expectNotToPerformAssertions();

it('will throw an exception for an invalid webhook url', function () {
    config()->set('slack-notifier.webhook_urls.default', 'not-an-url');

    SlackNotifier::send('test-data');
})->throws(WebhookUrlNotValid::class);

it('will throw an exception for an invalid formatter class', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');
    config()->set('slack-notifier.formatter', '');

    SlackNotifier::send('test-data');
})->throws(FormatterClassDoesNotExist::class);

it('will throw an exception for a missing formatter class', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');
    config()->set('slack-notifier.formatter', null);

    SlackNotifier::send('test-data');
})->throws(FormatterClassDoesNotExist::class);

it('does not include context in the default config context options', function () {
    expect(config('slack-notifier.context'))->not->toContain('context');
});

it('includes laravel context facade data in the context attachment when enabled in config', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');
    config()->set('slack-notifier.context', ['get', 'post', 'cookie', 'session', 'context']);

    Context::add('user_id', 123);
    Context::add('trace_id', 'abc-123');
    Context::addHidden('secret', 'hidden-value');

    SlackNotifier::send('test-data');

    Notification::assertSentOnDemand(SendToSlack::class, function (SendToSlack $notification, array $channels, $notifiable) {
        $slackMessage = $notification->toSlack($notifiable);
        $contextAttachment = end($slackMessage->attachments);

        expect($contextAttachment->pretext)->toBe('Context');
        expect($contextAttachment->content)
            ->toContain('$_CONTEXT =')
            ->toContain('[user_id] => 123')
            ->toContain('[trace_id] => abc-123')
            ->not->toContain('hidden-value')
            ->not->toContain('secret');

        return true;
    });
})->skip(fn () => ! class_exists(Context::class), 'Laravel Context facade requires Laravel 11+');

it('filters dont_flash keys from laravel context facade data', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');
    config()->set('slack-notifier.context', ['context']);

    Context::add([
        'user_id' => 123,
        'password' => 'secret-pass',
    ]);

    SlackNotifier::send('test-data');

    Notification::assertSentOnDemand(SendToSlack::class, function (SendToSlack $notification, array $channels, $notifiable) {
        $slackMessage = $notification->toSlack($notifiable);
        $contextAttachment = end($slackMessage->attachments);

        expect($contextAttachment->pretext)->toBe('Context');
        expect($contextAttachment->content)
            ->toContain('[user_id] => 123')
            ->not->toContain('password')
            ->not->toContain('secret-pass');

        return true;
    });
})->skip(fn () => ! class_exists(Context::class), 'Laravel Context facade requires Laravel 11+');

it('does not include empty or excluded laravel context facade data', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');
    config()->set('slack-notifier.context', ['context']);

    Context::add('password', 'secret-pass');

    SlackNotifier::send('test-data');

    Notification::assertSentOnDemand(SendToSlack::class, function (SendToSlack $notification, array $channels, $notifiable) {
        $slackMessage = $notification->toSlack($notifiable);
        $contextAttachment = end($slackMessage->attachments);

        expect($contextAttachment->pretext)->toBeNull();
        expect($contextAttachment->content)->toBeNull();

        return true;
    });
})->skip(fn () => ! class_exists(Context::class), 'Laravel Context facade requires Laravel 11+');

it('does not include laravel context facade data by default when context option is not configured', function () {
    config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

    Context::add('user_id', 123);

    SlackNotifier::send('test-data');

    Notification::assertSentOnDemand(SendToSlack::class, function (SendToSlack $notification, array $channels, $notifiable) {
        $slackMessage = $notification->toSlack($notifiable);
        $contextAttachment = end($slackMessage->attachments);

        expect($contextAttachment->pretext)->toBeNull();
        expect($contextAttachment->content)->toBeNull();

        return true;
    });
})->skip(fn () => ! class_exists(Context::class), 'Laravel Context facade requires Laravel 11+');
