<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure;

use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Run\RunFacadeInterface;

use function dirname;
use function in_array;
use function sprintf;

final readonly class PhelFnLoader implements PhelFnLoaderInterface
{
    private const PRIVATE_SYMBOLS = [
        Symbol::NAME_APPLY => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_CONCAT => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_DEF => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_DEF_STRUCT => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_DO => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_FN => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_FOREACH => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_IF => [
            'doc' => '```phel
(if test then else?)
```
A control flow structure. First evaluates test. If test evaluates to true, only the then form is evaluated and the result is returned. If test evaluates to false only the else form is evaluated and the result is returned. If no else form is given, nil will be returned.

The test evaluates to false if its value is false or equal to nil. Every other value evaluates to true. In sense of PHP this means (test != null && test !== false).',
            'fnSignature' => '(if test then else?)',
            'desc' => 'A control flow structure. First evaluates test. If test evaluates to true, only the then form is evaluated and the result is returned. If test evaluates to false only the else form is evaluated and the result is returned. If no else form is given, nil will be returned.',
        ],
        Symbol::NAME_LET => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_LOOP => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_NS => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_ARRAY_GET => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_ARRAY_PUSH => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_ARRAY_SET => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_ARRAY_UNSET => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_NEW => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_OBJECT_CALL => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_OBJECT_STATIC_CALL => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_QUOTE => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_RECUR => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_UNQUOTE => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_UNQUOTE_SPLICING => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_THROW => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_TRY => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_OBJECT_SET => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_LIST => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_VECTOR => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_MAP => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_SET_VAR => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_DEF_INTERFACE => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
    ];

    public function __construct(
        private RunFacadeInterface $runFacade,
    ) {
    }

    public function getNormalizedNativeSymbols(): array
    {
        return self::PRIVATE_SYMBOLS;
    }

    /**
     * @param  list<string>  $namespaces
     *
     * @return array<string,PersistentMapInterface>
     */
    public function getNormalizedPhelFunctions(array $namespaces = []): array
    {
        $this->loadAllPhelFunctions($namespaces);
        $containsCoreFunctions = in_array('phel\\core', $namespaces, true);

        /** @var array<string,PersistentMapInterface> $normalizedData */
        $normalizedData = [];
        foreach ($this->getNamespaces() as $ns) {
            if (!$containsCoreFunctions && $ns === 'phel\\core') {
                continue;
            }

            $normalizedNs = str_replace('phel\\', '', $ns);
            $moduleName = $normalizedNs === 'core' ? '' : $normalizedNs . '/';

            foreach (array_keys($this->getDefinitionsInNamespace($ns)) as $fnName) {
                $fullFnName = $moduleName . $fnName;

                $normalizedData[$fullFnName] = $this->getPhelMeta($ns, $fnName);
            }
        }

        ksort($normalizedData);

        return $normalizedData;
    }

    private function loadAllPhelFunctions(array $namespaces): void
    {
        Registry::getInstance()->clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $template = <<<EOF
# Simply require all namespaces that should be documented
(ns phel-internal\doc
  %REQUIRES%
)
EOF;
        $requireNamespaces = '';
        foreach ($namespaces as $ns) {
            $requireNamespaces .= sprintf('(:require %s)', $ns);
        }

        $docPhelContent = str_replace('%REQUIRES%', $requireNamespaces, $template);
        $phelFile = __DIR__ . '/phel/doc.phel';
        file_put_contents($phelFile, $docPhelContent);

        $namespace = $this->runFacade
            ->getNamespaceFromFile($phelFile)
            ->getNamespace();

        $srcDirectories = [
            dirname($phelFile),
            ...$this->runFacade->getAllPhelDirectories(),
        ];

        $namespaceInformation = $this->runFacade->getDependenciesForNamespace(
            $srcDirectories,
            [$namespace, 'phel\\core'],
        );

        foreach ($namespaceInformation as $info) {
            $this->runFacade->evalFile($info);
        }

        unlink($phelFile);
    }

    /**
     * @return list<string>
     */
    private function getNamespaces(): array
    {
        return Registry::getInstance()->getNamespaces();
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefinitionsInNamespace(string $ns): array
    {
        return Registry::getInstance()->getDefinitionInNamespace($ns);
    }

    private function getPhelMeta(string $ns, string $fnName): PersistentMapInterface
    {
        return Registry::getInstance()->getDefinitionMetaData($ns, $fnName)
            ?? TypeFactory::getInstance()->emptyPersistentMap();
    }
}
