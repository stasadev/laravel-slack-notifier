<?php

namespace Stasadev\SlackNotifier\Tests;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Mockery;
use RuntimeException;
use Stasadev\SlackNotifier\Exceptions\FormatterClassDoesNotExist;
use Stasadev\SlackNotifier\Exceptions\WebhookSendFail;
use Stasadev\SlackNotifier\Exceptions\WebhookUrlNotValid;
use Stasadev\SlackNotifier\Facades\SlackNotifier;
use stdClass;

class SlackNotifierTest extends TestCase
{
    public function test_it_sends_an_exception_to_the_default_webhook_url(): void
    {
        SlackNotifier::send(new RuntimeException('test-exception'));

        $this->assertSlackMessagesSent(1);
        $this->assertSame('https://default-domain.com', $this->getSentUrl());
    }

    public function test_it_sends_a_value_to_the_default_webhook_url(): void
    {
        foreach (['test-data', ['test-key' => 'test-data'], new stdClass, null, true] as $value) {
            SlackNotifier::send($value);
        }

        $this->assertSlackMessagesSent(5);

        foreach ($this->slack->sent as $sent) {
            $this->assertSame('https://default-domain.com', $sent['url']);
        }
    }

    public function test_it_does_not_send_its_own_webhook_send_fail_exception(): void
    {
        SlackNotifier::send(WebhookSendFail::make(new RuntimeException('webhook is failed')));

        $this->assertSlackMessagesSent(0);
    }

    public function test_it_sends_to_an_alternative_webhook_url_by_config_name(): void
    {
        config()->set('slack-notifier.webhook_urls.testing', 'https://testing-domain.com');

        SlackNotifier::to('testing')->send('test-data');

        $this->assertSame('https://testing-domain.com', $this->getSentUrl());
    }

    public function test_it_sends_to_a_webhook_url_given_directly(): void
    {
        SlackNotifier::to('https://custom-domain.com')->send('test-data');

        $this->assertSame('https://custom-domain.com', $this->getSentUrl());
    }

    public function test_it_sends_to_another_webhook_url_when_the_default_webhook_url_is_invalid(): void
    {
        config()->set('slack-notifier.webhook_urls.default', 'not-an-url');
        config()->set('slack-notifier.webhook_urls.testing', 'https://testing-domain.com');

        SlackNotifier::to('https://custom-domain.com')->send('first-value');
        SlackNotifier::to('testing')->send('second-value');

        $this->assertSame('https://custom-domain.com', $this->getSentUrl(0));
        $this->assertSame('https://testing-domain.com', $this->getSentUrl(1));
    }

    public function test_it_passes_no_webhook_url_when_the_webhook_url_is_empty(): void
    {
        config()->set('slack-notifier.webhook_urls.default', '');

        SlackNotifier::send('test-data');

        // The Slack webhook channel does not post anything without a URL.
        $this->assertNull($this->getSentUrl());
    }

    public function test_it_uses_the_channel_username_and_emoji_from_config(): void
    {
        config()->set('slack-notifier.channel', '#alerts');
        config()->set('slack-notifier.username', 'Alert Bot');
        config()->set('slack-notifier.emoji', ':fire:');

        SlackNotifier::send('test-data');

        $message = $this->getSentMessage();

        $this->assertSame('#alerts', $message->channel);
        $this->assertSame('Alert Bot', $message->username);
        $this->assertSame(':fire:', $message->icon);
    }

    public function test_it_overrides_the_channel_username_and_emoji(): void
    {
        SlackNotifier::channel('#random')
            ->username('Custom Bot')
            ->emoji(':tada:')
            ->send('test-data');

        $message = $this->getSentMessage();

        $this->assertSame('#random', $message->channel);
        $this->assertSame('Custom Bot', $message->username);
        $this->assertSame(':tada:', $message->icon);
    }

    public function test_it_reports_a_webhook_send_fail_exception_when_sending_fails(): void
    {
        $reported = null;

        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')->andReturnUsing(function ($exception) use (&$reported) {
            $reported = $exception;
        });
        $this->app->instance(ExceptionHandler::class, $handler);

        $this->slack->failWith = new RuntimeException('Slack is down');

        SlackNotifier::send('test-data');

        $this->assertInstanceOf(WebhookSendFail::class, $reported);
        $this->assertSame('RuntimeException: Slack is down', $reported->getMessage());
    }

    public function test_it_suppresses_the_same_exception_for_the_cache_seconds(): void
    {
        foreach ([1, 2, 3] as $attempt) {
            SlackNotifier::cacheSeconds(60)->send(new RuntimeException('same-exception'));
        }

        $this->assertSlackMessagesSent(1);
    }

    public function test_it_uses_the_cache_seconds_from_config(): void
    {
        config()->set('slack-notifier.cache_seconds', 60);

        foreach ([1, 2, 3] as $attempt) {
            SlackNotifier::send(new RuntimeException('same-exception'));
        }

        $this->assertSlackMessagesSent(1);
    }

    public function test_it_does_not_suppress_different_exceptions(): void
    {
        SlackNotifier::cacheSeconds(60)->send(new RuntimeException('first-exception'));
        SlackNotifier::cacheSeconds(60)->send(new RuntimeException('second-exception'));

        $this->assertSlackMessagesSent(2);
    }

    public function test_it_does_not_suppress_the_same_exception_without_cache_seconds(): void
    {
        foreach ([1, 2, 3] as $attempt) {
            SlackNotifier::send(new RuntimeException('same-exception'));
        }

        $this->assertSlackMessagesSent(3);
    }

    public function test_it_does_not_suppress_values_with_cache_seconds(): void
    {
        foreach ([1, 2, 3] as $attempt) {
            SlackNotifier::cacheSeconds(60)->send('same-value');
        }

        $this->assertSlackMessagesSent(3);
    }

    public function test_it_sends_a_value_after_an_exception(): void
    {
        SlackNotifier::send(new RuntimeException('test-exception'));
        SlackNotifier::send('test-data');

        $message = $this->getSentMessage(1);

        $this->assertSame('success', $message->level);
        $this->assertCount(2, $message->attachments);
        $this->assertSame('```test-data```', $message->attachments[0]->content);
    }

    public function test_it_sends_only_the_attachments_of_each_message(): void
    {
        SlackNotifier::send('first-value');
        SlackNotifier::send('second-value');

        $message = $this->getSentMessage(1);

        $this->assertCount(2, $message->attachments);
        $this->assertSame('```second-value```', $message->attachments[0]->content);
    }

    public function test_it_does_not_keep_the_options_for_the_next_message(): void
    {
        config()->set('slack-notifier.webhook_urls.testing', 'https://testing-domain.com');

        SlackNotifier::to('testing')->channel('#random')->send('first-value');
        SlackNotifier::send('second-value');

        $this->assertSame('https://default-domain.com', $this->getSentUrl(1));
        $this->assertNull($this->getSentMessage(1)->channel);
    }

    public function test_it_can_be_mocked_in_application_tests(): void
    {
        SlackNotifier::shouldReceive('send')->once()->with('test-data');

        SlackNotifier::send('test-data');

        $this->assertSlackMessagesSent(0);
    }

    public function test_it_will_throw_an_exception_for_a_non_existing_formatter_class(): void
    {
        config()->set('slack-notifier.formatter', 'non-existing-job');

        $this->expectException(FormatterClassDoesNotExist::class);

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
        config()->set('slack-notifier.formatter', '');

        $this->expectException(FormatterClassDoesNotExist::class);

        SlackNotifier::send('test-data');
    }

    public function test_it_will_throw_an_exception_for_a_missing_formatter_class(): void
    {
        config()->set('slack-notifier.formatter', null);

        $this->expectException(FormatterClassDoesNotExist::class);

        SlackNotifier::send('test-data');
    }
}
