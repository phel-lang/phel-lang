<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Repl;

use Generator;
use Phel\Command\Repl\InputValidator;
use Phel\Exceptions\WrongNumberOfParenthesisException;
use PHPUnit\Framework\TestCase;

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
            'input' => ['()'],
            'expected' => true,
        ];

        yield 'Calling a function' => [
            'input' => ['(+ 1 2)'],
            'expected' => true,
        ];

        yield 'Closing parenthesis missing' => [
            'input' => ['(+ 1 2'],
            'expected' => false,
        ];

        yield 'Only open parenthesis' => [
            'input' => ['('],
            'expected' => false,
        ];

        /* yield 'The closing parenthesis is after a comment sign' => [
             'input' => '(+ 1 2 #)',
             'expected' => false,
         ];*/
    }

    public function testWrongNumberOfParenthesis(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->inputValidator->isInputReadyToBeAnalyzed(['())']);
    }

    /*
    /**
     * @dataProvider providerWrongNumberOfParenthesis
     *
    public function testWrongNumberOfParenthesis(string $input): void
    {
        $this->expectException(WrongNumberOfParenthesisException::class);
        $this->inputValidator->isInputReadyToBeAnalyzed('');
    }

    public function providerWrongNumberOfParenthesis(): Generator
    {
        yield 'whe' => ['('];
    }*/
}
