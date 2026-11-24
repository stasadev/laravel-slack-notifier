<?php

namespace Stasadev\SlackNotifier\Tests;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Notifications\Messages\SlackMessage;
use Orchestra\Testbench\TestCase as Orchestra;
use Stasadev\SlackNotifier\SlackNotifierServiceProvider;
use Stasadev\SlackNotifier\Tests\Support\FakeSlackChannel;

class TestCase extends Orchestra
{
    /**
     * @var FakeSlackChannel
     */
    protected $slack;

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
     * The context attachment is always the last one, and it is empty when there is no context.
     */
    protected function getSentContextAttachment(int $index = 0): SlackAttachment
    {
        $attachments = $this->getSentMessage($index)->attachments;

        return end($attachments);
    }

    protected function assertNoContextAttachment(int $index = 0): void
    {
        $attachment = $this->getSentContextAttachment($index);

        $this->assertNull($attachment->pretext);
        $this->assertNull($attachment->content);
    }
}
