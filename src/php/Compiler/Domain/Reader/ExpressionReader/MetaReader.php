<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel;
use Phel\Compiler\Application\Reader;
use Phel\Compiler\Domain\Parser\ParserNode\MetaNode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\MetaInterface;
use Phel\Lang\Symbol;

use function count;
use function is_string;

final readonly class MetaReader
{
    public function __construct(private Reader $reader)
    {
    }

    /**
     * @throws ReaderException
     */
    public function read(MetaNode $node, NodeInterface $root): MetaInterface
    {
        $metaExpression = $node->getMetaNode();
        $objectExpression = $node->getObjectNode();

        $meta = $this->reader->readExpression($metaExpression, $root);
        if (is_string($meta) || $meta instanceof Symbol) {
            $meta = Phel::map(Keyword::create('tag'), $meta);
        } elseif ($meta instanceof Keyword) {
            $meta = Phel::map($meta, true);
        } elseif (!$meta instanceof PersistentMapInterface) {
            throw ReaderException::forNode($node, $root, 'Metadata must be a Symbol, String, Keyword or Map');
        }

        $object = $this->reader->readExpression($objectExpression, $root);

        if (!$object instanceof MetaInterface) {
            throw ReaderException::forNode($node, $root, 'Metadata can only applied to classes that implement MetaInterface');
        }

        $objMeta = $object->getMeta() ?? Phel::map();
        foreach ($meta as $k => $v) {
            if ($k) {
                $objMeta = $objMeta->put($k, $v);
            }
        }

        return $object->withMeta(count($objMeta) > 0 ? $objMeta : null);
    }
}
