<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader\ExpressionReader;

use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Reader\Reader;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;

final class ListFnReader
{
    private Reader $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    public function read(ListNode $node, ?array &$fnArgs, NodeInterface $root): PersistentListInterface
    {
        $body = (new ListReader($this->reader))->read($node, $root);
        $params = $this->extractParams($fnArgs);
        $fnArgs = null;

        return TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FN),
            TypeFactory::getInstance()->persistentVectorFromArray($params),
            $body,
        ])->setStartLocation($node->getStartLocation())->setEndLocation($node->getEndLocation());
    }

    private function extractParams(?array $fnArgs): array
    {
        if (empty($fnArgs)) {
            return [];
        }

        $params = [];

        for ($i = 1, $maxParams = max(array_keys($fnArgs)); $i <= $maxParams; $i++) {
            if (isset($fnArgs[$i])) {
                $params[] = Symbol::create($fnArgs[$i]->getName());
            } else {
                $params[] = Symbol::gen('__short_fn_undefined_');
            }
        }

        if (isset($fnArgs[0])) {
            $params[] = Symbol::create('&');
            $params[] = Symbol::create($fnArgs[0]->getName());
        }

        return $params;
    }
}
