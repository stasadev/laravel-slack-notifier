<?php

namespace Stasadev\SlackNotifier\Tests;

use Illuminate\Support\Facades\Context;
use Stasadev\SlackNotifier\Facades\SlackNotifier;

class ContextTest extends TestCase
{
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
}
