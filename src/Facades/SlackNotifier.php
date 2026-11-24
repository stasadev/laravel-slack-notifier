<?php

namespace Stasadev\SlackNotifier\Facades;

use Illuminate\Support\Facades\Facade;
use Stasadev\SlackNotifier\Notifications\SendToSlack;

/**
 * @method static SendToSlack to(string $to)
 * @method static SendToSlack channel(?string $channel)
 * @method static SendToSlack username(string $username)
 * @method static SendToSlack emoji(string $emoji)
 * @method static SendToSlack cacheSeconds(int $cacheSeconds)
 * @method static void send($message)
 *
 * @see SendToSlack
 */
class SlackNotifier extends Facade
{
    protected static function getFacadeAccessor()
    {
        return SendToSlack::class;
    }

    /**
     * Resolve a new notification for every call.
     *
     * A notification keeps its options, like to() and channel(),
     * so reusing one would carry them over to the next message.
     */
    protected static function resolveFacadeInstance($name)
    {
        // Keep the instance swapped in by SlackNotifier::swap() or SlackNotifier::shouldReceive().
        if (isset(static::$resolvedInstance[$name])) {
            return static::$resolvedInstance[$name];
        }

        if (static::$app) {
            return static::$app[$name];
        }
    }
}
