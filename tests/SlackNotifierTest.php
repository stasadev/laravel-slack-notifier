<?php

namespace Stasadev\SlackNotifier\Tests;

use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Stasadev\SlackNotifier\Exceptions\FormatterClassDoesNotExist;
use Stasadev\SlackNotifier\Exceptions\WebhookSendFail;
use Stasadev\SlackNotifier\Exceptions\WebhookUrlNotValid;
use Stasadev\SlackNotifier\Facades\SlackNotifier;
use stdClass;

class SlackNotifierTest extends TestCase
{
    public function test_it_can_send_a_notification_with_an_exception_to_slack_using_the_default_webhook_url(): void
    {
        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

        SlackNotifier::send(new RuntimeException('test-exception'));

        $this->assertSlackNotificationsSent(1);
    }

    public function test_it_cannot_send_a_notification_with_its_fail_to_slack_using_the_default_webhook_url(): void
    {
        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

        SlackNotifier::send(WebhookSendFail::make(new RuntimeException('webhook is failed')));

        Notification::assertNothingSent();
    }

    public function test_it_can_send_a_notification_with_a_message_to_slack_using_the_default_webhook_url(): void
    {
        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

        SlackNotifier::send('test-data');

        $this->assertSlackNotificationsSent(1);
    }

    public function test_it_can_send_a_notification_with_an_array_to_slack_using_the_default_webhook_url(): void
    {
        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

        SlackNotifier::send([
            'test-key' => 'test-data',
        ]);

        $this->assertSlackNotificationsSent(1);
    }

    public function test_it_can_send_a_notification_with_an_object_to_slack_using_the_default_webhook_url(): void
    {
        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

        SlackNotifier::send(new stdClass);

        $this->assertSlackNotificationsSent(1);
    }

    public function test_it_can_send_a_notification_with_null_to_slack_using_the_default_webhook_url(): void
    {
        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

        SlackNotifier::send(null);

        $this->assertSlackNotificationsSent(1);
    }

    public function test_it_can_send_a_notification_with_boolean_to_slack_using_the_default_webhook_url(): void
    {
        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

        SlackNotifier::send(true);

        $this->assertSlackNotificationsSent(1);
    }

    public function test_it_can_send_a_notification_with_a_message_to_slack_using_an_alternative_webhook_url(): void
    {
        config()->set('slack-notifier.webhook_urls.testing', 'https://default-domain.com');

        SlackNotifier::to('testing')->send('test-data');

        $this->assertSlackNotificationsSent(1);
    }

    public function test_it_can_send_a_notification_with_a_message_to_slack_alternative_channel(): void
    {
        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');

        SlackNotifier::channel('random')->send('test-data');

        $this->assertSlackNotificationsSent(1);
    }

    public function test_it_will_throw_an_exception_for_a_non_existing_formatter_class(): void
    {
        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');
        config()->set('slack-notifier.formatter', 'non-existing-job');

        $this->expectException(FormatterClassDoesNotExist::class);

        SlackNotifier::send('test-data');
    }

    public function test_it_will_not_throw_an_exception_for_an_empty_webhook_url(): void
    {
        config()->set('slack-notifier.webhook_urls.default', '');

        $this->expectNotToPerformAssertions();

        SlackNotifier::send('test-data');
    }

    public function test_it_will_throw_an_exception_for_an_invalid_webhook_url(): void
    {
        config()->set('slack-notifier.webhook_urls.default', 'not-an-url');

        $this->expectException(WebhookUrlNotValid::class);

        SlackNotifier::send('test-data');
    }

    public function test_it_will_throw_an_exception_for_an_invalid_formatter_class(): void
    {
        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');
        config()->set('slack-notifier.formatter', '');

        $this->expectException(FormatterClassDoesNotExist::class);

        SlackNotifier::send('test-data');
    }

    public function test_it_will_throw_an_exception_for_a_missing_formatter_class(): void
    {
        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');
        config()->set('slack-notifier.formatter', null);

        $this->expectException(FormatterClassDoesNotExist::class);

        SlackNotifier::send('test-data');
    }
}
