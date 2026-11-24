<?php

namespace Stasadev\SlackNotifier\Tests;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Notifications\Messages\SlackMessage;
use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;
use Stasadev\SlackNotifier\SlackNotifierServiceProvider;
use Stasadev\SlackNotifier\Tests\Support\FakeSlackChannel;

class TestCase extends Orchestra
{
    /**
     * @var FakeSlackChannel
     */
    protected $slack;

    /**
     * @var \Throwable[]
     */
    protected $reported = [];

    protected function setUp(): void
    {
        parent::setUp();

        $slack = $this->slack = new FakeSlackChannel;

        $this->app->make(ChannelManager::class)->extend('slack', function () use ($slack) {
            return $slack;
        });

        config()->set('app.name', 'Testing App');
        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');
    }

    protected function getPackageProviders($app): array
    {
        return [
            SlackNotifierServiceProvider::class,
        ];
    }

    /**
     * Record the exceptions passed to report() in $this->reported, instead of logging them.
     */
    protected function recordReportedExceptions(): void
    {
        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')->andReturnUsing(function ($exception) {
            $this->reported[] = $exception;
        });

        $this->app->instance(ExceptionHandler::class, $handler);
    }

    protected function assertSlackMessagesSent(int $count): void
    {
        $this->assertCount($count, $this->slack->sent);
    }

    protected function getSentMessage(int $index = 0): SlackMessage
    {
        $this->assertArrayHasKey($index, $this->slack->sent, "Slack message #{$index} was not sent.");

        return $this->slack->sent[$index]['message'];
    }

    protected function getSentUrl(int $index = 0): ?string
    {
        $this->assertArrayHasKey($index, $this->slack->sent, "Slack message #{$index} was not sent.");

        return $this->slack->sent[$index]['url'];
    }

    /**
     * The context attachment is always the last one.
     */
    protected function getSentContextAttachment(int $index = 0): SlackAttachment
    {
        $attachments = $this->getSentMessage($index)->attachments;
        $attachment = end($attachments);

        $this->assertSame('Context', $attachment->pretext, "Slack message #{$index} has no context attachment.");

        return $attachment;
    }

    protected function assertNoContextAttachment(int $index = 0): void
    {
        foreach ($this->getSentMessage($index)->attachments as $attachment) {
            $this->assertNotSame('Context', $attachment->pretext, "Slack message #{$index} has a context attachment.");
            $this->assertNotNull($attachment->content, "Slack message #{$index} has an empty attachment.");
        }
    }
}
