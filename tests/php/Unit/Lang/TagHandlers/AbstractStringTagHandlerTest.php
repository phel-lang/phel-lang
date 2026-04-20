<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\TagHandlers;

use Phel\Lang\TagHandlerException;
use Phel\Lang\TagHandlers\AbstractStringTagHandler;
use PHPUnit\Framework\TestCase;

final class AbstractStringTagHandlerTest extends TestCase
{
    public function test_it_returns_the_concrete_result_for_a_string_form(): void
    {
        $handler = $this->uppercaseHandler();

        self::assertSame('HELLO', $handler('hello'));
    }

    public function test_it_throws_when_form_is_not_a_string(): void
    {
        $handler = $this->uppercaseHandler();

        $this->expectException(TagHandlerException::class);
        $this->expectExceptionMessage('#shout expects a string literal (e.g. #shout "hi").');

        $handler(42);
    }

    public function test_it_omits_example_parenthetical_when_empty(): void
    {
        $handler = new readonly class() extends AbstractStringTagHandler {
            protected function tagName(): string
            {
                return 'quiet';
            }

            protected function example(): string
            {
                return '';
            }

            protected function handleString(string $form): string
            {
                return $form;
            }
        };

        $this->expectException(TagHandlerException::class);
        $this->expectExceptionMessage('#quiet expects a string literal.');

        $handler(1);
    }

    private function uppercaseHandler(): AbstractStringTagHandler
    {
        return new readonly class() extends AbstractStringTagHandler {
            protected function tagName(): string
            {
                return 'shout';
            }

            protected function example(): string
            {
                return '#shout "hi"';
            }

            protected function handleString(string $form): string
            {
                return strtoupper($form);
            }
        };
    }
}
