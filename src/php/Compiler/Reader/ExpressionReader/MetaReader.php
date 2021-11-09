<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader\ExpressionReader;

use Phel\Compiler\Parser\ParserNode\MetaNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Reader\Exceptions\ReaderException;
use Phel\Compiler\Reader\Reader;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\MetaInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Lang\TypeInterface;

final class MetaReader
{
    private Reader $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * @throws ReaderException
     *
     * @return TypeInterface|string|float|int|bool
     */
    public function read(MetaNode $node, NodeInterface $root)
    {
        $metaExpression = $node->getMetaNode();
        $objectExpression = $node->getObjectNode();

        $meta = $this->reader->readExpression($metaExpression, $root);
        if (is_string($meta) || $meta instanceof Symbol) {
            $meta = TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('tag'), $meta);
        } elseif ($meta instanceof Keyword) {
            $meta = TypeFactory::getInstance()->persistentMapFromKVs($meta, true);
        } elseif (!$meta instanceof PersistentMapInterface) {
            throw ReaderException::forNode($node, $root, 'Metadata must be a Symbol, String, Keyword or Map');
        }
        $object = $this->reader->readExpression($objectExpression, $root);

        if (!$object instanceof MetaInterface) {
            throw ReaderException::forNode($node, $root, 'Metadata can only applied to classes that implement MetaInterface');
        }

        $objMeta = $object->getMeta() ?? TypeFactory::getInstance()->emptyPersistentMap();
        foreach ($meta as $k => $v) {
            if ($k) {
                $objMeta = $objMeta->put($k, $v);
            }
        }

        return $object->withMeta(count($objMeta) > 0 ? $objMeta : null);
    }
}
