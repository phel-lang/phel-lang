<?php

declare(strict_types=1);

namespace Phel\Lint\Infrastructure;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\FnInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lint\Domain\CoreFunctionNamesInterface;

/**
 * Reads the public `phel.core` functions from the runtime registry on first
 * use. The lint command loads `phel.core` before any rule runs; when nothing
 * loaded it the set is empty and no binding is reported.
 *
 * @internal
 */
final class RegistryCoreFunctionNames implements CoreFunctionNamesInterface
{
    private const string CORE_NAMESPACE = 'phel.core';

    /** @var array<string, true>|null */
    private ?array $names = null;

    public function contains(string $name): bool
    {
        $this->names ??= $this->load();

        return isset($this->names[$name]);
    }

    /**
     * @return array<string, true>
     */
    private function load(): array
    {
        $registry = Registry::getInstance();
        $private = Keyword::create('private');
        $macro = Keyword::create('macro');

        $names = [];
        foreach ($registry->getDefinitionInNamespace(self::CORE_NAMESPACE) as $name => $value) {
            if (!$value instanceof FnInterface) {
                continue;
            }

            $meta = $registry->getDefinitionMetaData(self::CORE_NAMESPACE, $name);
            if ($meta instanceof PersistentMapInterface
                && ($meta->find($private) === true || $meta->find($macro) === true)
            ) {
                continue;
            }

            $names[$name] = true;
        }

        return $names;
    }
}
