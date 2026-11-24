<?php

namespace Stasadev\SlackNotifier\Tests;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Stasadev\SlackNotifier\Exceptions\FormatterClassDoesNotExist;
use Stasadev\SlackNotifier\Exceptions\WebhookSendFail;
use Stasadev\SlackNotifier\Exceptions\WebhookUrlDoesNotExist;
use Stasadev\SlackNotifier\Exceptions\WebhookUrlNotValid;
use Stasadev\SlackNotifier\Facades\SlackNotifier;
use Stasadev\SlackNotifier\Notifications\SendToSlack;
use stdClass;
use Throwable;

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

    public static function packageExceptionProvider(): array
    {
        return [
            'FormatterClassDoesNotExist' => [FormatterClassDoesNotExist::make('App\\Formatters\\Missing')],
            'WebhookSendFail' => [WebhookSendFail::make(new RuntimeException('webhook is failed'))],
            'WebhookUrlDoesNotExist' => [WebhookUrlDoesNotExist::make('testing')],
            'WebhookUrlNotValid' => [WebhookUrlNotValid::make('testing', 'not-an-url')],
        ];
    }

    /**
     * @dataProvider packageExceptionProvider
     */
    #[DataProvider('packageExceptionProvider')]
    public function test_it_does_not_send_its_own_exceptions(RuntimeException $exception): void
    {
        SlackNotifier::send($exception);

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

    public function test_it_does_not_send_without_a_webhook_url(): void
    {
        foreach (['', null] as $url) {
            config()->set('slack-notifier.webhook_urls.default', $url);

            SlackNotifier::send('test-data');
            SlackNotifier::send(new RuntimeException('test-exception'));
        }

        $this->assertSlackMessagesSent(0);
    }

    public function test_it_does_not_suppress_an_exception_that_was_not_sent_without_a_webhook_url(): void
    {
        $exception = new RuntimeException('test-exception');

        config()->set('slack-notifier.webhook_urls.default', '');
        SlackNotifier::cacheSeconds(60)->send($exception);

        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');
        SlackNotifier::cacheSeconds(60)->send($exception);

        $this->assertSlackMessagesSent(1);
        $this->assertSame('https://default-domain.com', $this->getSentUrl());
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
        $this->recordReportedExceptions();

        $this->slack->failWith = new RuntimeException('Slack is down');

        SlackNotifier::send('test-data');

        $this->assertCount(1, $this->reported);
        $this->assertInstanceOf(WebhookSendFail::class, $this->reported[0]);
        $this->assertSame('RuntimeException: Slack is down', $this->reported[0]->getMessage());
    }

    public function test_it_reports_a_webhook_send_fail_exception_when_the_cache_fails(): void
    {
        $this->recordReportedExceptions();

        Cache::shouldReceive('add')->andThrow(new RuntimeException('Connection refused'));

        SlackNotifier::cacheSeconds(60)->send(new RuntimeException('test-exception'));

        $this->assertSlackMessagesSent(0);
        $this->assertCount(1, $this->reported);
        $this->assertInstanceOf(WebhookSendFail::class, $this->reported[0]);
        $this->assertSame('RuntimeException: Connection refused', $this->reported[0]->getMessage());
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

    public function test_it_does_not_suppress_an_exception_that_failed_to_send(): void
    {
        $this->recordReportedExceptions();

        $exception = new RuntimeException('test-exception');

        $this->slack->failWith = new RuntimeException('Slack is down');
        SlackNotifier::cacheSeconds(60)->send($exception);

        $this->slack->failWith = null;
        SlackNotifier::cacheSeconds(60)->send($exception);
        SlackNotifier::cacheSeconds(60)->send($exception);

        $this->assertSlackMessagesSent(1);
        $this->assertCount(1, $this->reported);
        $this->assertInstanceOf(WebhookSendFail::class, $this->reported[0]);
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

    public function test_it_sends_a_value_after_an_exception_with_the_same_notification(): void
    {
        $notification = SlackNotifier::channel('#random');

        $notification->send(new RuntimeException('test-exception'));
        $notification->send('test-data');

        $message = $this->getSentMessage(1);

        $this->assertSame('success', $message->level);
        $this->assertSame('```test-data```', $message->attachments[0]->content);
        $this->assertStringNotContainsString('test-exception', $this->getSentContents(1));
        $this->assertSame('#random', $message->channel);
    }

    public function test_it_sends_an_exception_after_a_value_with_the_same_notification(): void
    {
        $notification = SlackNotifier::channel('#random');

        $notification->send('test-data');
        $notification->send(new RuntimeException('test-exception'));

        $message = $this->getSentMessage(1);

        $this->assertSame('error', $message->level);
        $this->assertStringContainsString('RuntimeException: test-exception', $message->attachments[0]->content);
        $this->assertStringNotContainsString('test-data', $this->getSentContents(1));
    }

    public function test_it_sends_only_the_attachments_of_each_message_with_the_same_notification(): void
    {
        $notification = SlackNotifier::channel('#random');

        $notification->send('first-value');
        $notification->send('second-value');

        $this->assertSame('```second-value```', $this->getSentMessage(1)->attachments[0]->content);
        $this->assertStringNotContainsString('first-value', $this->getSentContents(1));
    }

    public function test_it_sends_with_a_notification_resolved_from_the_container(): void
    {
        $notification = $this->app->make(SendToSlack::class);

        $notification->send('first-value');
        $notification->send('second-value');

        $this->assertSlackMessagesSent(2);
        $this->assertSame('```second-value```', $this->getSentMessage(1)->attachments[0]->content);
        $this->assertStringNotContainsString('first-value', $this->getSentContents(1));
    }

    /**
     * Get the content of all attachments of a sent message.
     */
    protected function getSentContents(int $index): string
    {
        return implode("\n", array_map(function ($attachment) {
            return (string) $attachment->content;
        }, $this->getSentMessage($index)->attachments));
    }

    public function test_it_can_be_mocked_in_application_tests(): void
    {
        SlackNotifier::shouldReceive('send')->once()->with('test-data');

        SlackNotifier::send('test-data');

        $this->assertSlackMessagesSent(0);
    }

    public static function configErrorProvider(): array
    {
        return [
            'unknown webhook name' => [[], 'non-existing', WebhookUrlDoesNotExist::class],
            'invalid webhook URL' => [['webhook_urls.default' => 'not-an-url'], 'default', WebhookUrlNotValid::class],
            'non-existing formatter class' => [['formatter' => 'non-existing-job'], 'default', FormatterClassDoesNotExist::class],
            'empty formatter class' => [['formatter' => ''], 'default', FormatterClassDoesNotExist::class],
            'missing formatter class' => [['formatter' => null], 'default', FormatterClassDoesNotExist::class],
        ];
    }

    /**
     * @dataProvider configErrorProvider
     */
    #[DataProvider('configErrorProvider')]
    public function test_it_reports_a_config_error_instead_of_throwing(array $config, string $to, string $expected): void
    {
        $this->recordReportedExceptions();

        foreach ($config as $key => $value) {
            config()->set("slack-notifier.{$key}", $value);
        }

        SlackNotifier::to($to)->send(new RuntimeException('test-exception'));

        $this->assertSlackMessagesSent(0);
        $this->assertCount(1, $this->reported);
        $this->assertInstanceOf($expected, $this->reported[0]);
    }

    public function test_it_sends_with_a_null_username_and_emoji_in_config(): void
    {
        config()->set('slack-notifier.username', null);
        config()->set('slack-notifier.emoji', null);

        SlackNotifier::send('test-data');

        $this->assertSlackMessagesSent(1);
    }

    public function test_it_lets_laravel_log_the_exception_when_the_config_is_invalid(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (! method_exists($handler, 'reportable')) {
            $this->markTestSkipped('Report callbacks require Laravel 8+');
        }

        config()->set('slack-notifier.webhook_urls.default', 'not-an-url');

        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            // Laravel 5.7 passes the message, and later versions pass a MessageLogged event.
            $logged[] = is_object($event) ? $event->message : $event;
        });

        $handler->reportable(function (Throwable $e) {
            SlackNotifier::send($e);
        });

        $handler->report(new RuntimeException('test-exception'));

        // The config error is reported from the callback, before Laravel logs the original exception.
        $this->assertSlackMessagesSent(0);
        $this->assertCount(2, $logged);
        $this->assertStringContainsString('`not-an-url`', $logged[0]);
        $this->assertSame('test-exception', $logged[1]);
    }
}
