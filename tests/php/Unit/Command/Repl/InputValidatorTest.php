<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Repl;

use Generator;
use Phel\Command\Repl\InputValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InputValidatorTest extends TestCase
{
    private InputValidator $inputValidator;

    public function setUp(): void
    {
        $this->inputValidator = new InputValidator();
    }

    /**
     * @dataProvider providerInputReady
     */
    public function testInputReady(array $inputBuffer, bool $expected): void
    {
        self::assertEquals(
            $expected,
            $this->inputValidator->isInputReadyToBeAnalyzed($inputBuffer)
        );
    }

    public function providerInputReady(): Generator
    {
        yield 'An empty list' => [
            'inputBuffer' => ['()'],
            'expected' => true,
        ];

        yield 'An empty list in multiline' => [
            'inputBuffer' => ['(', ')'],
            'expected' => true,
        ];

        yield 'Calling a function' => [
            'inputBuffer' => ['(+ 1 2)'],
            'expected' => true,
        ];

        yield 'Calling a function in multiline' => [
            'inputBuffer' => ['(+ 1', '2', ')'],
            'expected' => true,
        ];

        yield 'Function call in multiline with a comment in the middle' => [
            'inputBuffer' => ['(+ 1', '2', '#)', ')'],
            'expected' => true,
        ];

        yield 'Closing parenthesis missing' => [
            'inputBuffer' => ['(+ 1 2'],
            'expected' => false,
        ];

        yield 'Closing parenthesis missing in multiline' => [
            'inputBuffer' => ['(+ 1', '2 3'],
            'expected' => false,
        ];

        yield 'Only open parenthesis' => [
            'inputBuffer' => ['('],
            'expected' => false,
        ];

        yield 'The closing parenthesis is after a comment sign' => [
            'inputBuffer' => ['(+ 1 2 #)'],
            'expected' => false,
        ];
    }

    public function testWrongNumberOfParentheses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wrong number of parentheses');
        $this->inputValidator->isInputReadyToBeAnalyzed(['())']);
    }

    public function testWrongNumberOfBrackets(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wrong number of brackets');
        $this->inputValidator->isInputReadyToBeAnalyzed(['[]]']);
    }

    public function testWrongNumberOfBraces(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wrong number of braces');
        $this->inputValidator->isInputReadyToBeAnalyzed(['{}}']);
    }
}
