<?php

use Illuminate\Support\Facades\Notification;
use Stasadev\SlackNotifier\Exceptions\FormatterClassDoesNotExist;
use Stasadev\SlackNotifier\Exceptions\WebhookSendFail;
use Stasadev\SlackNotifier\Exceptions\WebhookUrlNotValid;
use Stasadev\SlackNotifier\Facades\SlackNotifier;
use Stasadev\SlackNotifier\Notifications\SendToSlack;

beforeEach(function () {
    Notification::fake();
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
