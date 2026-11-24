<?php

namespace Stasadev\SlackNotifier\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Stasadev\SlackNotifier\Config;
use Stasadev\SlackNotifier\Exceptions\WebhookSendFail;
use Stasadev\SlackNotifier\SlackNotifierFormatter;
use Throwable;

class SendToSlack extends Notification
{
    use Queueable;

    /**
     * @var Throwable|null
     */
    protected $exception;

    /**
     * @var mixed
     */
    protected $variable;

    /**
     * A webhook URL, or the name of a webhook URL in config.
     *
     * @var string
     */
    protected $to;

    /**
     * @var null|string
     */
    protected $channel;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $emoji;

    /**
     * @var int
     */
    protected $cacheSeconds;

    /**
     * @var SlackNotifierFormatter
     */
    protected $formatter;

    public function __construct()
    {
        $this->to('default')
            ->channel(config('slack-notifier.channel', ''))
            ->username(config('slack-notifier.username', 'Laravel Log'))
            ->emoji(config('slack-notifier.emoji', ':boom:'))
            ->cacheSeconds((int) config('slack-notifier.cache_seconds', 0));

        $this->formatter = Config::getFormatter();
    }

    public function to(string $to): self
    {
        $this->to = $to;

        return $this;
    }

    public function channel(?string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function username(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function emoji(string $emoji): self
    {
        $this->emoji = $emoji;

        return $this;
    }

    public function cacheSeconds(int $cacheSeconds): self
    {
        $this->cacheSeconds = $cacheSeconds;

        return $this;
    }

    public function send($message): void
    {
        if ($message instanceof Throwable) {
            $this->exception = $message;
        } else {
            $this->variable = $message;
        }

        if ($this->exception instanceof WebhookSendFail) {
            return;
        }

        // Resolve only the webhook URL that is used, so an invalid one does not affect the others.
        $url = Config::getWebhookUrl($this->to);

        // An empty webhook URL disables sending, so skip building the message and caching the exception.
        if (! $url) {
            return;
        }

        try {
            NotificationFacade::route('slack', $url)->notify($this);
        } catch (Throwable $e) {
            report(WebhookSendFail::make($e));
        }
    }

    public function via($notifiable): array
    {
        return $this->cached() ? [] : ['slack'];
    }

    public function toSlack($notifiable): SlackMessage
    {
        $slackMessage = $this->formatter->format($this);

        return $slackMessage->from($this->username, $this->emoji)
            ->to($this->channel);
    }

    public function getException(): ?Throwable
    {
        return $this->exception;
    }

    public function getVariable()
    {
        return $this->variable;
    }

    protected function cached(): bool
    {
        // don't cache unless there are exceptions
        if (! $this->exception) {
            return false;
        }

        $seconds = $this->cacheSeconds;

        if ($seconds < 1) {
            return false;
        }

        $key = Str::kebab($this->username.' Slack Log Message')
            .'-'.sha1($this->exception);

        if (cache()->get($key)) {
            return true;
        }

        cache()->set($key, true, $seconds);

        return false;
    }
}
