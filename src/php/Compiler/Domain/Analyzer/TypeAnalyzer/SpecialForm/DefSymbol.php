<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Lang\TypeInterface;

use function assert;
use function count;
use function in_array;
use function is_scalar;
use function is_string;

final class DefSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    private const POSSIBLE_TUPLE_SIZES = [3, 4];

    /**
     * @throws AbstractLocatedException
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): DefNode
    {
        $this->ensureDefIsAllowed($list, $env);
        $this->verifySizeOfTuple($list);

        $nameSymbol = $list->get(1);
        if (!($nameSymbol instanceof Symbol)) {
            throw AnalyzerException::withLocation("First argument of 'def must be a Symbol.", $list);
        }

        $namespace = $this->analyzer->getNamespace();

        if (!$this->analyzer->hasDefinition($namespace, $nameSymbol)) {
            $this->analyzer->addDefinition($namespace, $nameSymbol);
        } else {
            throw AnalyzerException::definitionDuplication($list, $namespace, $nameSymbol);
        }

        [$metaMap, $init] = $this->createMetaMapAndInit($list);

        $initNode = $this->analyzeInit($init, $env, $namespace, $nameSymbol);
        if ($initNode instanceof FnNode) {
            $metaMap = $metaMap->put('min-arity', $initNode->getMinArity());
        }

        $meta = $this->analyzer->analyze($metaMap, $env->withExpressionContext());
        assert($meta instanceof MapNode);

        return new DefNode(
            $env,
            $namespace,
            $nameSymbol,
            $meta,
            $initNode,
            $list->getStartLocation(),
        );
    }

    private function ensureDefIsAllowed(PersistentListInterface $list, NodeEnvironmentInterface $env): void
    {
        if (!$env->isDefAllowed()) {
            throw AnalyzerException::withLocation("'def inside of a 'def is forbidden", $list);
        }
    }

    private function verifySizeOfTuple(PersistentListInterface $list): void
    {
        $listSize = count($list);

        if (!in_array($listSize, self::POSSIBLE_TUPLE_SIZES)) {
            throw AnalyzerException::withLocation(
                "Two or three arguments are required for 'def. Got " . $listSize,
                $list,
            );
        }
    }

    /**
     * @return array{0:PersistentMapInterface, 1:mixed}
     */
    private function createMetaMapAndInit(PersistentListInterface $list): array
    {
        [$meta, $init] = $this->getInitialMetaAndInit($list);

        if (!($init instanceof TypeInterface)
            && !is_scalar($init)
            && $init !== null
        ) {
            throw AnalyzerException::withLocation('$init must be TypeInterface|string|float|int|bool|null', $list);
        }

        $meta = $this->normalizeMeta($meta, $list);

        $listMeta = $list->getMeta();
        if ($listMeta instanceof PersistentMapInterface) {
            foreach ($listMeta->getIterator() as $key => $value) {
                if ($key !== null) {
                    $meta = $meta->put($key, $value);
                }
            }
        }

        $startLocation = $list->getStartLocation();
        if ($startLocation instanceof SourceLocation) {
            $meta = $meta->put(Keyword::create('start-location'), TypeFactory::getInstance()->persistentMapFromKVs(
                Keyword::create('file'),
                $startLocation->getFile(),
                Keyword::create('line'),
                $startLocation->getLine(),
                Keyword::create('column'),
                $startLocation->getColumn(),
            ));
        }

        $endLocation = $list->getEndLocation();
        if ($endLocation instanceof SourceLocation) {
            $meta = $meta->put(Keyword::create('end-location'), TypeFactory::getInstance()->persistentMapFromKVs(
                Keyword::create('file'),
                $endLocation->getFile(),
                Keyword::create('line'),
                $endLocation->getLine(),
                Keyword::create('column'),
                $endLocation->getColumn(),
            ));
        }

        return [$meta, $init];
    }

    private function normalizeMeta(mixed $meta, PersistentListInterface $list): PersistentMapInterface
    {
        if (is_string($meta)) {
            $key = (Keyword::create('doc'))->copyLocationFrom($list);

            return TypeFactory::getInstance()
                ->persistentMapFromKVs($key, $meta)
                ->copyLocationFrom($list);
        }

        if ($meta instanceof Keyword) {
            return TypeFactory::getInstance()
                ->persistentMapFromKVs($meta, true)
                ->copyLocationFrom($meta);
        }

        if ($meta instanceof PersistentMapInterface) {
            return $meta;
        }

        throw AnalyzerException::withLocation('Metadata must be a String, Keyword or Map', $list);
    }

    private function getInitialMetaAndInit(PersistentListInterface $list): array
    {
        if (count($list) === 3) {
            return [TypeFactory::getInstance()->emptyPersistentMap(), $list->get(2)];
        }

        return [$list->get(2), $list->get(3)];
    }

    private function analyzeInit(
        float|bool|int|string|TypeInterface|null $init,
        NodeEnvironmentInterface $env,
        string $namespace,
        Symbol $nameSymbol,
    ): AbstractNode {
        $initEnv = $env
            ->withBoundTo($namespace . '\\' . $nameSymbol)
            ->withExpressionContext()
            ->withDisallowRecurFrame()
            ->withDefAllowed(false);

        return $this->analyzer->analyze($init, $initEnv);
    }
}
