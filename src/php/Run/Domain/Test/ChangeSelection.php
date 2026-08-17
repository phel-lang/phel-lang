<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Test;

/**
 * What `--changed` resolved: the changed `.phel` files that the scan knows,
 * and the test namespaces they can affect.
 *
 * @internal
 */
final readonly class ChangeSelection
{
    /**
     * @param list<string> $changedFiles   absolute paths, only files the scan knows
     * @param list<string> $testNamespaces affected test namespaces, scan order
     */
    public function __construct(
        public array $changedFiles,
        public array $testNamespaces,
    ) {}
}
