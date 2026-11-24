<?php

namespace Stasadev\SlackNotifier\Tests;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Notification;
use Stasadev\SlackNotifier\Facades\SlackNotifier;
use Stasadev\SlackNotifier\Notifications\SendToSlack;

class ContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');
    }

    public function test_it_does_not_include_context_in_the_default_config_context_options(): void
    {
        $this->assertNotContains('context', config('slack-notifier.context'));
    }

    public function test_it_includes_laravel_context_facade_data_in_the_context_attachment_when_enabled_in_config(): void
    {
        $this->requireContextFacade();

        config()->set('slack-notifier.context', ['get', 'post', 'cookie', 'session', 'context']);

        Context::add('user_id', 123);
        Context::add('trace_id', 'abc-123');
        Context::addHidden('secret', 'hidden-value');

        SlackNotifier::send('test-data');

        $attachment = $this->getSentContextAttachment();

        $this->assertSame('Context', $attachment->pretext);
        $this->assertStringContainsString('$_CONTEXT =', $attachment->content);
        $this->assertStringContainsString('[user_id] => 123', $attachment->content);
        $this->assertStringContainsString('[trace_id] => abc-123', $attachment->content);
        $this->assertStringNotContainsString('hidden-value', $attachment->content);
        $this->assertStringNotContainsString('secret', $attachment->content);
    }

    public function test_it_filters_dont_flash_keys_from_laravel_context_facade_data(): void
    {
        $this->requireContextFacade();

        config()->set('slack-notifier.context', ['context']);

        Context::add([
            'user_id' => 123,
            'password' => 'secret-pass',
        ]);

        SlackNotifier::send('test-data');

        $attachment = $this->getSentContextAttachment();

        $this->assertSame('Context', $attachment->pretext);
        $this->assertStringContainsString('[user_id] => 123', $attachment->content);
        $this->assertStringNotContainsString('password', $attachment->content);
        $this->assertStringNotContainsString('secret-pass', $attachment->content);
    }

    public function test_it_does_not_include_empty_or_excluded_laravel_context_facade_data(): void
    {
        $this->requireContextFacade();

        config()->set('slack-notifier.context', ['context']);

        Context::add('password', 'secret-pass');

        SlackNotifier::send('test-data');

        $this->assertNoContextAttachment();
    }

    public function test_it_does_not_include_laravel_context_facade_data_by_default_when_context_option_is_not_configured(): void
    {
        $this->requireContextFacade();

        Context::add('user_id', 123);

        SlackNotifier::send('test-data');

        $this->assertNoContextAttachment();
    }

    public function test_it_ignores_the_context_option_when_the_laravel_context_facade_is_not_available(): void
    {
        if (class_exists(Context::class)) {
            $this->markTestSkipped('Laravel Context facade is available in Laravel 11+');
        }

        config()->set('slack-notifier.context', ['context']);

        SlackNotifier::send('test-data');

        $this->assertNoContextAttachment();
    }

    protected function requireContextFacade(): void
    {
        if (! class_exists(Context::class)) {
            $this->markTestSkipped('Laravel Context facade requires Laravel 11+');
        }
    }

    protected function getSentContextAttachment(): SlackAttachment
    {
        $attachment = null;

        Notification::assertSentTo(new AnonymousNotifiable, SendToSlack::class, function (SendToSlack $notification, array $channels, $notifiable) use (&$attachment) {
            $attachments = $notification->toSlack($notifiable)->attachments;
            $attachment = end($attachments);

            return true;
        });

        $this->assertInstanceOf(SlackAttachment::class, $attachment);

        return $attachment;
    }

    protected function assertNoContextAttachment(): void
    {
        $attachment = $this->getSentContextAttachment();

        $this->assertNull($attachment->pretext);
        $this->assertNull($attachment->content);
    }
}
