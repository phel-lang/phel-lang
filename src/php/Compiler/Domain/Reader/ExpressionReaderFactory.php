<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader;

use Phel\Compiler\Application\Reader;
use Phel\Compiler\Domain\Reader\ExpressionReader\AtomReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\ListFnReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\ListReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\MapReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\MetaReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\QuoasiquoteReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\SymbolReader;
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

    public function createListReader(Reader $reader): ListReader
    {
        return new ListReader($reader);
    }

    public function createVectorReader(Reader $reader): VectorReader
    {
        return new VectorReader($reader);
    }

    public function createListFnReader(Reader $reader): ListFnReader
    {
        return new ListFnReader($reader);
    }

    public function createWrapReader(Reader $reader): WrapReader
    {
        return new WrapReader($reader);
    }

    public function createQuoasiquoteReader(
        Reader $reader,
        QuasiquoteTransformerInterface $quasiquoteTransformer,
    ): QuoasiquoteReader {
        return new QuoasiquoteReader($reader, $quasiquoteTransformer);
    }

    public function createMetaReader(Reader $reader): MetaReader
    {
        return new MetaReader($reader);
    }

    public function createMapReader(Reader $reader): MapReader
    {
        return new MapReader($reader);
    }
}
