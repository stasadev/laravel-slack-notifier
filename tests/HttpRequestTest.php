<?php

namespace Stasadev\SlackNotifier\Tests;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\NullSessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Stasadev\SlackNotifier\Facades\SlackNotifier;

/**
 * Runs the package as if it handled an HTTP request instead of a console command.
 */
class HttpRequestTest extends TestCase
{
    protected function setUp(): void
    {
        // Laravel reads it when the application asks whether it runs in the console.
        $this->setRunningInConsole('false');

        parent::setUp();

        $request = Request::create(
            '/orders?page=2',
            'POST',
            ['name' => 'John', 'password' => 'secret-pass'],
            ['theme' => 'dark'],
            ['avatar' => UploadedFile::fake()->create('avatar.jpg')],
            ['HTTP_X_REQUEST_ID' => 'abc-123']
        );

        $session = new Store('test', new NullSessionHandler);
        $session->put('cart_id', 7);
        $request->setLaravelSession($session);

        $this->app->instance('request', $request);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->setRunningInConsole(null);
    }

    public function test_it_uses_the_request_url_in_the_pretext_of_a_value(): void
    {
        SlackNotifier::send('test-data');

        $this->assertSame('Received value from http://localhost/orders', $this->getSentMessage()->attachments[0]->pretext);
    }

    public function test_it_uses_the_request_url_in_the_pretext_of_an_exception(): void
    {
        SlackNotifier::send(new RuntimeException('test-exception'));

        $this->assertSame('Caught an exception from http://localhost/orders', $this->getSentMessage()->attachments[0]->pretext);
    }

    public static function contextItemProvider(): array
    {
        return [
            'get' => ['get', '$_GET = ', ['[page] => 2']],
            'post' => ['post', '$_POST = ', ['[name] => John']],
            'request' => ['request', '$_REQUEST = ', ['[page] => 2', '[name] => John']],
            'headers' => ['headers', '$_HEADERS = ', ['[x-request-id]', 'abc-123']],
            'files' => ['files', '$_FILES = ', ['[avatar]']],
            'cookie' => ['cookie', '$_COOKIE = ', ['[theme] => dark']],
            'session' => ['session', '$_SESSION = ', ['[cart_id] => 7']],
            'server' => ['server', '$_SERVER = ', ['[REQUEST_METHOD] => POST']],
        ];
    }

    /**
     * @dataProvider contextItemProvider
     */
    #[DataProvider('contextItemProvider')]
    public function test_it_includes_request_data_in_the_context_attachment(string $item, string $prefix, array $expected): void
    {
        config()->set('slack-notifier.context', [$item]);

        SlackNotifier::send('test-data');

        $attachment = $this->getSentContextAttachment();

        $this->assertSame('Context', $attachment->pretext);
        $this->assertStringStartsWith('```'.$prefix, $attachment->content);
        $this->assertSame('#3498DB', $attachment->color);
        $this->assertSame(['text'], $attachment->markdown);

        foreach ($expected as $text) {
            $this->assertStringContainsString($text, $attachment->content);
        }
    }

    public function test_it_includes_get_post_cookie_and_session_data_by_default(): void
    {
        SlackNotifier::send('test-data');

        $content = $this->getSentContextAttachment()->content;

        $this->assertStringContainsString('$_GET = ', $content);
        $this->assertStringContainsString('$_POST = ', $content);
        $this->assertStringContainsString('$_COOKIE = ', $content);
        $this->assertStringContainsString('$_SESSION = ', $content);
        $this->assertStringNotContainsString('$_SERVER = ', $content);
    }

    public function test_it_filters_dont_flash_keys_from_request_data(): void
    {
        config()->set('slack-notifier.context', ['post']);

        SlackNotifier::send('test-data');

        $content = $this->getSentContextAttachment()->content;

        $this->assertStringContainsString('[name] => John', $content);
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('secret-pass', $content);
    }

    public function test_it_skips_the_session_when_the_request_has_no_session(): void
    {
        $this->app->instance('request', Request::create('/orders'));

        config()->set('slack-notifier.context', ['session']);

        SlackNotifier::send('test-data');

        $this->assertNoContextAttachment();
    }

    public function test_it_ignores_unknown_context_items(): void
    {
        config()->set('slack-notifier.context', ['unknown']);

        SlackNotifier::send('test-data');

        $this->assertNoContextAttachment();
    }

    protected function setRunningInConsole(?string $value): void
    {
        if ($value === null) {
            unset($_ENV['APP_RUNNING_IN_CONSOLE'], $_SERVER['APP_RUNNING_IN_CONSOLE']);
            putenv('APP_RUNNING_IN_CONSOLE');

            return;
        }

        $_ENV['APP_RUNNING_IN_CONSOLE'] = $_SERVER['APP_RUNNING_IN_CONSOLE'] = $value;
        putenv("APP_RUNNING_IN_CONSOLE={$value}");
    }
}
