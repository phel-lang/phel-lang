<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader;

use Phel\Compiler\Domain\Reader\ExpressionReader\AtomReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\ListFnReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\ListReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\MapReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\MetaReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\QuoasiquoteReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\SetReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\SymbolReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\TaggedLiteralReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\VectorReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\WrapReader;

final class ExpressionReaderFactory implements ExpressionReaderFactoryInterface
{
    public function createSymbolReader(): SymbolReader
    {
        return new SymbolReader();
    }

    public function createAtomReader(): AtomReader
    {
        return new AtomReader();
    }

    public function createListReader(ReaderInterface $reader): ListReader
    {
        return new ListReader($reader);
    }

    public function createVectorReader(ReaderInterface $reader): VectorReader
    {
        return new VectorReader($reader);
    }

    public function createSetReader(ReaderInterface $reader): SetReader
    {
        return new SetReader($reader);
    }

    public function createListFnReader(ReaderInterface $reader): ListFnReader
    {
        return new ListFnReader($reader);
    }

    public function createWrapReader(ReaderInterface $reader): WrapReader
    {
        return new WrapReader($reader);
    }

    public function createQuoasiquoteReader(
        ReaderInterface $reader,
        QuasiquoteTransformerInterface $quasiquoteTransformer,
    ): QuoasiquoteReader {
        return new QuoasiquoteReader($reader, $quasiquoteTransformer);
    }

    public function createMetaReader(ReaderInterface $reader): MetaReader
    {
        return new MetaReader($reader);
    }

    public function createMapReader(ReaderInterface $reader): MapReader
    {
        return new MapReader($reader);
    }

    public function createTaggedLiteralReader(ReaderInterface $reader): TaggedLiteralReader
    {
        return new TaggedLiteralReader($reader);
    }
}
