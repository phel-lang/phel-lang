<?php

declare(strict_types=1);

namespace PhelTest\Support\Fixtures\PhpInterop;

use LogicException;
use Stringable;

final class NativeReturnFixture
{
    public function either(): ReturnTypeA|ReturnTypeB
    {
        throw new LogicException();
    }

    public function reversed(): ReturnTypeB|ReturnTypeA
    {
        throw new LogicException();
    }

    public function compound(): (ReturnTypeA&Stringable)|ReturnTypeB
    {
        throw new LogicException();
    }

    public function unknown(): ReturnTypeA|MissingReturnType
    {
        throw new LogicException();
    }

    public function both(): ReturnTypeA&ReturnTypeB
    {
        throw new LogicException();
    }

    public function withPrivate(): ReturnTypeA&PrivateReturnBase
    {
        throw new LogicException();
    }

    public function maybeFalse(): ReturnTypeA|false
    {
        return false;
    }

    public function maybeNull(): ?ReturnTypeA
    {
        return null;
    }
}
