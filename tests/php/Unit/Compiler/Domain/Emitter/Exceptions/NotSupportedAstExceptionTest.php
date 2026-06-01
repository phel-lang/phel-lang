<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\Exceptions;

use Phel\Compiler\Domain\Emitter\Exceptions\NotSupportedAstException;
use PHPUnit\Framework\TestCase;

final class NotSupportedAstExceptionTest extends TestCase
{
    public function test_message_names_the_unsupported_node(): void
    {
        $exception = NotSupportedAstException::withClassName('SomeNode');

        self::assertStringContainsString('SomeNode', $exception->getMessage());
    }

    public function test_message_points_to_the_factory_for_remediation(): void
    {
        $exception = NotSupportedAstException::withClassName('SomeNode');

        self::assertStringContainsString('NodeEmitterFactory::instantiateEmitter', $exception->getMessage());
    }
}
