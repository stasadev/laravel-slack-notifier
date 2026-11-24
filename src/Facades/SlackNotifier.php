<?php

namespace Stasadev\SlackNotifier\Facades;

use Illuminate\Support\Facades\Facade;
use Stasadev\SlackNotifier\Notifications\SendToSlack;

/**
 * @method static self to(string $to)
 * @method static self channel(?string $channel)
 * @method static self username(string $username)
 * @method static self emoji(string $emoji)
 * @method static self cacheSeconds(int $cacheSeconds)
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
}
