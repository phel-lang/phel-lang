<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

/**
 * A reflected PHP class for hover: its name, kind (`class`, `final class`,
 * `interface`, `enum`, ...), parent and implemented interfaces, cleaned class
 * phpdoc, and the constructor signature when the class declares one.
 */
final readonly class PhpInteropClass
{
    /**
     * @param list<string> $interfaces
     */
    public function __construct(
        public string $name,
        public string $kind,
        public ?string $parent,
        public array $interfaces,
        public string $documentation = '',
        public ?PhpInteropSignature $constructor = null,
    ) {}
}
