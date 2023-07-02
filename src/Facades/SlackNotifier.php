<?php

namespace Stasadev\SlackNotifier\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static self to(string $to)
 * @method static self channel(?string $channel)
 * @method static self username(string $username)
 * @method static self emoji(string $emoji)
 * @method static self cacheSeconds(int $cacheSeconds)
 * @method static void send($message)
 *
 * @see \Stasadev\SlackNotifier\Notifications\SendToSlack
 */
class SlackNotifier extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Stasadev\SlackNotifier\Notifications\SendToSlack::class;
    }
}
