<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions\Hint;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Throwable;
use TypeError;

use function sprintf;

final readonly class PhpArrayArgumentHint implements ExceptionHintInterface
{
    public function appliesTo(Throwable $e): bool
    {
        return $this->collectionKind($e) !== null;
    }

    public function hint(Throwable $e): string
    {
        return sprintf(
            'PHP expects an array, but received a Phel %s. Use (to-array value) for a shallow conversion or (phel->php value) for nested conversion; check the resulting keys and values.',
            $this->collectionKind($e) ?? 'collection',
        );
    }

    private function collectionKind(Throwable $e): ?string
    {
        if (!$e instanceof TypeError || preg_match('/Argument #\d+ \([^)]*\) must be of type \??array, ([^,\s]+) given/', $e->getMessage(), $match) !== 1) {
            return null;
        }

        $class = $match[1];
        if (!class_exists($class, false)) {
            return null;
        }

        if (is_a($class, PersistentMapInterface::class, true)) {
            return 'map';
        }

        return is_a($class, PersistentVectorInterface::class, true) ? 'vector' : null;
    }
}
