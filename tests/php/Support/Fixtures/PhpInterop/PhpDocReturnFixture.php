<?php

declare(strict_types=1);

namespace PhelTest\Support\Fixtures\PhpInterop;

use DateTime;
use DateTimeImmutable as Response;

abstract class PhpDocReturnFixture
{
    /**
     * @return Response
     */
    abstract public function response();

    /**
     * @return DateTime
     */
    abstract public function qualified();

    /**
     * @return self
     */
    abstract public function fluent();

    /**
     * @return static
     */
    abstract public function lateStatic();

    abstract public function nativeScalar(): int;

    /**
     * @return Response
     */
    abstract public function nativeObject(): object;

    /**
     * @return Response
     */
    abstract public function nativeClass(): ChainFixture;

    /**
     * @return false|Response
     */
    abstract public function unsupportedUnion();

    /**
     * @return Response<int>
     */
    abstract public function unsupportedGeneric();

    /**
     * @return array{broken
     */
    abstract public function malformed();

    /**
     * @return MissingClass
     */
    abstract public function missing();

    /**
     * @return Response
     * @return array{broken
     */
    abstract public function conflictingReturns();

    /**
     * @return array{broken
     * @return Response
     */
    abstract public function invalidThenValidReturn();
}
