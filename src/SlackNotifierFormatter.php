<?php

namespace Stasadev\SlackNotifier;

use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Support\Arr;
use Monolog\Formatter\NormalizerFormatter;
use ReflectionMethod;
use Stasadev\SlackNotifier\Notifications\SendToSlack;
use Throwable;

class SlackNotifierFormatter
{
    /**
     * @var SlackMessage
     */
    protected $message;

    /**
     * @var NormalizerFormatter
     */
    protected $normalizer;

    /**
     * @var string[]
     */
    protected $context;

    /**
     * @var string[]
     */
    protected $dontFlash;

    /**
     * @var string[]
     */
    protected $dontTrace;

    public function __construct()
    {
        $this->message = new SlackMessage();
        $this->normalizer = new NormalizerFormatter();

        $this->context = config('slack-notifier.context', [
            'get', 'post', 'cookie', 'session',
        ]);

        $this->dontFlash = config('slack-notifier.dont_flash', [
            'current_password',
            'password',
            'password_confirmation',
        ]);

        $this->dontTrace = config('slack-notifier.dont_trace', [
            '/vendor/symfony/',
            '/vendor/laravel/framework/',
            '/vendor/barryvdh/laravel-debugbar/',
        ]);
    }

    public function format(SendToSlack $notification): SlackMessage
    {
        if ($exception = $notification->getException()) {
            $slackMessage = $this->formatException($exception);
        } else {
            $slackMessage = $this->formatVariable($notification->getVariable());
        }

        $slackMessage->attachment(function (SlackAttachment $attachment) {
            if (! $context = $this->getContext()) {
                return;
            }

            $attachment->pretext('Context')
                ->content('```'.$context.'```')
                ->color('#3498DB')
                ->markdown(['text']);
        });

        return $slackMessage;
    }

    protected function formatException(Throwable $exception, ?SlackMessage $slackMessage = null): SlackMessage
    {
        $pretext = $this->getPretext($exception);

        if ($slackMessage) {
            $pretext = 'Previous exception';
        }

        $this->message->error();

        $this->message->attachment(function (SlackAttachment $attachment) use ($exception, $pretext) {
            $content = $this->normalize(get_class($exception).': '.$exception->getMessage().' in '.$exception->getFile().':'.$exception->getLine());

            $attachment->pretext($pretext)
                ->content('```'.$content.'```')
                ->fallback(config('app.name').': '.$content)
                ->markdown(['text']);
        });

        $this->message->attachment(function (SlackAttachment $attachment) use ($exception) {
            $attachment->pretext('Stack trace')
                ->content('```'.$this->normalizeTrace($exception).'```');
        });

        if ($previous = $exception->getPrevious()) {
            return $this->formatException($previous, $this->message);
        }

        return $this->message;
    }

    protected function formatVariable($variable): SlackMessage
    {
        $this->message->success();

        $variable = $this->normalizeToString($variable);

        $this->message->attachment(function (SlackAttachment $attachment) use ($variable) {
            $attachment->pretext($this->getPretext($variable))
                ->content('```'.$variable.'```')
                ->fallback(config('app.name').': '.$variable)
                ->markdown(['text']);
        });

        return $this->message;
    }

    protected function getPretext($variable): string
    {
        $source = app()->runningInConsole() ? 'console' : request()->url();

        if ($variable instanceof Throwable) {
            return 'Caught an exception from '.$source;
        }

        return 'Received value from '.$source;
    }

    protected function normalize($data)
    {
        if (method_exists($this->normalizer, 'normalizeValue')) {
            return $this->normalizer->normalizeValue($data);
        }

        $r = new ReflectionMethod($this->normalizer, 'normalize');
        $r->setAccessible(true);

        return $r->invoke($this->normalizer, $data);
    }

    protected function normalizeToString($variable): string
    {
        $variable = $this->normalize($variable);

        try {
            if (is_null($variable)) {
                $string = 'null';
            } elseif (is_bool($variable)) {
                $string = $variable ? 'true' : 'false';
            } elseif (is_array($variable)) {
                $string = print_r($variable, true);
            } elseif (is_object($variable)) {
                $string = json_encode($variable);
            } else {
                $string = (string) $variable;
            }
        } catch (Throwable $e) {
            $string = 'Failed to normalize variable.';
        }

        return $string;
    }

    protected function normalizeTrace(Throwable $exception): string
    {
        $emptyLineCharacter = '   ...';
        $lines = explode("\n", $exception->getTraceAsString());
        $filteredLines = [];

        foreach ($lines as $line) {
            $shouldExclude = false;
            foreach ($this->dontTrace as $excludePattern) {
                if (str_starts_with($line, '#') && str_contains($line, $excludePattern)) {
                    $shouldExclude = true;
                    break;
                }
            }

            if ($shouldExclude && end($filteredLines) !== $emptyLineCharacter) {
                $filteredLines[] = $emptyLineCharacter;
            } elseif (! $shouldExclude) {
                $filteredLines[] = $line;
            }
        }

        return implode("\n", $filteredLines);
    }

    protected function getContext(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        $context = [];

        foreach ($this->context as $item) {
            $value = null;
            $format = '$_%s = %s';

            if ($item === 'get') {
                $value = request()->query();
            } elseif ($item === 'post') {
                $value = request()->post();
            } elseif ($item === 'request') {
                $value = request()->all();
            } elseif ($item === 'headers') {
                $value = request()->headers->all();
            } elseif ($item === 'files') {
                $value = request()->allFiles();
            } elseif ($item === 'cookie') {
                $value = request()->cookie();
            } elseif ($item === 'session' && request()->hasSession()) {
                $value = request()->session()->all();
            } elseif ($item === 'server') {
                $value = request()->server();
            }

            if (is_array($value) && ($value = Arr::except($value, $this->dontFlash))) {
                $context[] = sprintf(
                    $format,
                    strtoupper($item),
                    print_r($this->normalize($value), true)
                );
            }
        }

        return implode("\n", $context);
    }
}
