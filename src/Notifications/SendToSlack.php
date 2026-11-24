<?php

namespace Stasadev\SlackNotifier\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Stasadev\SlackNotifier\Config;
use Stasadev\SlackNotifier\Exceptions\SlackNotifierException;
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
            ->username((string) config('slack-notifier.username', 'Laravel Log'))
            ->emoji((string) config('slack-notifier.emoji', ':boom:'))
            ->cacheSeconds((int) config('slack-notifier.cache_seconds', 0));
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

        // The package reports its own exceptions, so sending them to Slack could loop.
        if ($this->exception instanceof SlackNotifierException) {
            return;
        }

        try {
            $this->sendToSlack();
        } catch (Throwable $e) {
            // send() usually runs while Laravel reports an exception, and an exception thrown here
            // would stop Laravel from logging that exception, so report the error instead.
            report($e instanceof SlackNotifierException ? $e : WebhookSendFail::make($e));
        }
    }

    protected function sendToSlack(): void
    {
        // Resolve only the webhook URL that is used, so an invalid one does not affect the others.
        $url = Config::getWebhookUrl($this->to);

        // An empty webhook URL disables sending, so skip building the message and caching the exception.
        if (! $url) {
            return;
        }

        $this->formatter = Config::getFormatter();

        $cacheKey = $this->getCacheKey();

        // Cache::add() only stores the key if it is missing, so the same exception is also not sent twice at the same time.
        // Use a date, as Laravel 5.7 reads a number as minutes.
        if ($cacheKey && ! cache()->add($cacheKey, true, now()->addSeconds($this->cacheSeconds))) {
            return;
        }

        try {
            NotificationFacade::route('slack', $url)->notify($this);
        } catch (Throwable $e) {
            // Do not suppress an exception that did not reach Slack.
            if ($cacheKey) {
                cache()->forget($cacheKey);
            }

            throw $e;
        }
    }

    public function via($notifiable): array
    {
        return ['slack'];
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

    /**
     * Get the key that suppresses the same exception for the cache seconds.
     */
    protected function getCacheKey(): ?string
    {
        // Only exceptions are suppressed.
        if (! $this->exception || $this->cacheSeconds < 1) {
            return null;
        }

        return Str::kebab($this->username.' Slack Log Message')
            .'-'.sha1($this->exception);
    }
}
