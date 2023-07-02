<?php

return [
    /*
     * Slack incoming webhook URLs.
     */
    'webhook_urls' => [
        'default' => env('LOG_SLACK_WEBHOOK_URL'),
    ],

    /*
     * Override the Slack channel to which the message will be sent.
     */
    'channel' => env('LOG_SLACK_CHANNEL'),

    /*
     * The name of the Slack bot.
     */
    'username' => env('APP_NAME', 'Laravel Log'),

    /*
     * The emoji used for the Slack bot.
     */
    'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),

    /*
     * An exception can be triggered several times in a row.
     * We can use the Laravel cache to suppress the same exception for "x" seconds.
     */
    'cache_seconds' => env('LOG_SLACK_CACHE_SECONDS', 0),

    /*
     * A formatter for Slack message.
     */
    'formatter' => Stasadev\SlackNotifier\SlackNotifierFormatter::class,

    /*
     * Add context for the Slack message. Possible values:
     * 'get', 'post', 'request', 'headers', 'files', 'cookie', 'session', 'server'
     */
    'context' => [
        'get',
        'post',
        'cookie',
        'session',
    ],

    /*
     * The list of the values from context that are never flashed to Slack.
     */
    'dont_flash' => [
        'current_password',
        'password',
        'password_confirmation',
    ],

    /*
     * Lines containing any of these strings will be excluded from exceptions.
     */
    'dont_trace' => [
        '/vendor/symfony/',
        '/vendor/laravel/framework/',
        '/vendor/barryvdh/laravel-debugbar/',
    ],
];
