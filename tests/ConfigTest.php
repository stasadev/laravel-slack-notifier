<?php

namespace Stasadev\SlackNotifier\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Stasadev\SlackNotifier\Config;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('slack-notifier.webhook_urls.default', 'https://default-domain.com');
    }

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
}
