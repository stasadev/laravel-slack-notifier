<?php

namespace Stasadev\SlackNotifier\Tests;

use LogicException;
use RuntimeException;
use Stasadev\SlackNotifier\Exceptions\FormatterClassDoesNotExist;
use Stasadev\SlackNotifier\Exceptions\WebhookSendFail;
use Stasadev\SlackNotifier\Exceptions\WebhookUrlDoesNotExist;
use Stasadev\SlackNotifier\Exceptions\WebhookUrlNotValid;

class ExceptionsTest extends TestCase
{
    public function test_it_names_the_class_in_formatter_class_does_not_exist(): void
    {
        $exception = FormatterClassDoesNotExist::make('App\\Formatters\\Missing');

        $this->assertStringContainsString("'App\\Formatters\\Missing' does not exist", $exception->getMessage());
    }

    public function test_it_accepts_no_class_in_formatter_class_does_not_exist(): void
    {
        $exception = FormatterClassDoesNotExist::make(null);

        $this->assertStringContainsString("formatter class '' does not exist", $exception->getMessage());
    }

    public function test_it_names_the_config_key_in_webhook_url_does_not_exist(): void
    {
        $exception = WebhookUrlDoesNotExist::make('testing');

        $this->assertStringContainsString('`testing` webhook does not exist', $exception->getMessage());
        $this->assertStringContainsString('`webhook_urls.testing`', $exception->getMessage());
    }

    public function test_it_names_the_config_key_and_url_in_webhook_url_not_valid(): void
    {
        $exception = WebhookUrlNotValid::make('testing', 'not-an-url');

        $this->assertStringContainsString('`testing` webhook contains an invalid url `not-an-url`', $exception->getMessage());
        $this->assertStringContainsString('`webhook_urls.testing`', $exception->getMessage());
    }

    public function test_it_wraps_the_original_exception_in_webhook_send_fail(): void
    {
        $previous = new LogicException('root-cause');

        $exception = WebhookSendFail::make(new RuntimeException('Slack is down', 5, $previous));

        $this->assertSame('RuntimeException: Slack is down', $exception->getMessage());
        $this->assertSame(1, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
