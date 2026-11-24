<?php

namespace Stasadev\SlackNotifier\Tests;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Stasadev\SlackNotifier\Facades\SlackNotifier;
use Stasadev\SlackNotifier\Tests\Support\CustomFormatter;
use stdClass;

class FormatterTest extends TestCase
{
    public static function valueProvider(): array
    {
        return [
            'string' => ['test-data', 'test-data'],
            'integer' => [42, '42'],
            'float' => [1.5, '1.5'],
            'true' => [true, 'true'],
            'false' => [false, 'false'],
            'null' => [null, 'null'],
            'array' => [['key' => 'value'], print_r(['key' => 'value'], true)],
        ];
    }

    /**
     * @dataProvider valueProvider
     */
    #[DataProvider('valueProvider')]
    public function test_it_formats_a_value($value, string $expected): void
    {
        SlackNotifier::send($value);

        $message = $this->getSentMessage();

        $this->assertSame('success', $message->level);
        $this->assertCount(2, $message->attachments);

        $attachment = $message->attachments[0];

        $this->assertSame('Received value from console', $attachment->pretext);
        $this->assertSame('```'.$expected.'```', $attachment->content);
        $this->assertSame('Testing App: '.$expected, $attachment->fallback);
        $this->assertSame(['text'], $attachment->markdown);

        $this->assertNoContextAttachment();
    }

    public function test_it_formats_an_object(): void
    {
        $object = new stdClass;
        $object->name = 'John';

        SlackNotifier::send($object);

        $content = $this->getSentMessage()->attachments[0]->content;

        $this->assertStringContainsString('stdClass', $content);
        $this->assertStringContainsString('John', $content);
    }

    public function test_it_formats_an_exception(): void
    {
        $line = __LINE__ + 1;
        $exception = new RuntimeException('test-exception');

        SlackNotifier::send($exception);

        $message = $this->getSentMessage();

        $this->assertSame('error', $message->level);
        $this->assertCount(3, $message->attachments);

        $expected = 'RuntimeException: test-exception in '.__FILE__.':'.$line;

        $this->assertSame('Caught an exception from console', $message->attachments[0]->pretext);
        $this->assertSame('```'.$expected.'```', $message->attachments[0]->content);
        $this->assertSame('Testing App: '.$expected, $message->attachments[0]->fallback);
        $this->assertSame(['text'], $message->attachments[0]->markdown);

        $this->assertSame('Stack trace', $message->attachments[1]->pretext);
        $this->assertStringStartsWith('```#0 ', $message->attachments[1]->content);

        $this->assertNoContextAttachment();
    }

    public function test_it_formats_previous_exceptions(): void
    {
        SlackNotifier::send(new RuntimeException('outer-exception', 0, new LogicException('inner-exception')));

        $attachments = $this->getSentMessage()->attachments;

        $this->assertCount(5, $attachments);

        $this->assertSame('Caught an exception from console', $attachments[0]->pretext);
        $this->assertStringContainsString('RuntimeException: outer-exception', $attachments[0]->content);
        $this->assertSame('Stack trace', $attachments[1]->pretext);

        $this->assertSame('Previous exception', $attachments[2]->pretext);
        $this->assertStringContainsString('LogicException: inner-exception', $attachments[2]->content);
        $this->assertSame('Stack trace', $attachments[3]->pretext);
    }

    public function test_it_replaces_excluded_stack_trace_lines_with_one_line(): void
    {
        config()->set('slack-notifier.dont_trace', ['/vendor/']);

        SlackNotifier::send($this->createException());

        $trace = trim($this->getSentMessage()->attachments[1]->content, '`');
        $lines = explode("\n", $trace);

        // The test method that created the exception, then the PHPUnit frames from /vendor/.
        $this->assertStringContainsString('createException()', $lines[0]);
        $this->assertSame('   ...', $lines[1]);
        $this->assertStringEndsWith('{main}', end($lines));
        $this->assertStringNotContainsString('/vendor/', $trace);

        foreach (array_keys($lines) as $index) {
            if ($index > 0 && $lines[$index] === '   ...') {
                $this->assertNotSame('   ...', $lines[$index - 1], 'Consecutive excluded lines must be replaced with one line.');
            }
        }
    }

    public function test_it_keeps_the_full_stack_trace_without_excluded_lines(): void
    {
        config()->set('slack-notifier.dont_trace', []);

        $exception = $this->createException();

        SlackNotifier::send($exception);

        $this->assertSame('```'.$exception->getTraceAsString().'```', $this->getSentMessage()->attachments[1]->content);
    }

    public function test_it_uses_a_custom_formatter_from_config(): void
    {
        config()->set('slack-notifier.formatter', CustomFormatter::class);

        SlackNotifier::send('test-data');

        $message = $this->getSentMessage();

        $this->assertSame('Formatted by a custom formatter', $message->content);
        $this->assertSame('```test-data```', $message->attachments[0]->content);
    }

    protected function createException(): RuntimeException
    {
        return new RuntimeException('test-exception');
    }
}
