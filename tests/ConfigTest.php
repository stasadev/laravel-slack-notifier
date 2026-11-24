<?php

namespace Stasadev\SlackNotifier\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Stasadev\SlackNotifier\Config;
use Stasadev\SlackNotifier\Exceptions\FormatterClassDoesNotExist;
use Stasadev\SlackNotifier\Exceptions\WebhookUrlNotValid;
use Stasadev\SlackNotifier\SlackNotifierFormatter;
use Stasadev\SlackNotifier\Tests\Support\CustomFormatter;

class ConfigTest extends TestCase
{
    public static function webhookUrlProvider(): array
    {
        return [
            ['default', 'https://default-domain.com'],
            ['https://custom-domain.com', 'https://custom-domain.com'],
        ];
    }

    /**
     * @dataProvider webhookUrlProvider
     */
    #[DataProvider('webhookUrlProvider')]
    public function test_it_can_get_a_webhook_url(string $name, string $result): void
    {
        $this->assertSame($result, Config::getWebhookUrl($name));
    }

    public function test_it_cannot_get_a_webhook_url_for_an_unknown_config_name(): void
    {
        $this->assertNull(Config::getWebhookUrl('non-existing'));
    }

    public function test_it_cannot_get_a_webhook_url_for_an_empty_config_value(): void
    {
        config()->set('slack-notifier.webhook_urls.default', '');

        $this->assertNull(Config::getWebhookUrl('default'));
    }

    public function test_it_throws_an_exception_for_an_invalid_webhook_url_in_config(): void
    {
        config()->set('slack-notifier.webhook_urls.default', 'not-an-url');

        $this->expectException(WebhookUrlNotValid::class);

        Config::getWebhookUrl('default');
    }

    public function test_it_gets_the_default_formatter(): void
    {
        $this->assertInstanceOf(SlackNotifierFormatter::class, Config::getFormatter());
    }

    public function test_it_gets_a_custom_formatter_from_config(): void
    {
        config()->set('slack-notifier.formatter', CustomFormatter::class);

        $this->assertInstanceOf(CustomFormatter::class, Config::getFormatter());
    }

    public function test_it_throws_an_exception_for_a_non_existing_formatter_class(): void
    {
        config()->set('slack-notifier.formatter', 'App\\Formatters\\Missing');

        $this->expectException(FormatterClassDoesNotExist::class);

        Config::getFormatter();
    }
}
