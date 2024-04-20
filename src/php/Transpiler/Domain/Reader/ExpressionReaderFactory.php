<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Reader;

use Phel\Transpiler\Domain\Reader\ExpressionReader\AtomReader;
use Phel\Transpiler\Domain\Reader\ExpressionReader\ListFnReader;
use Phel\Transpiler\Domain\Reader\ExpressionReader\ListReader;
use Phel\Transpiler\Domain\Reader\ExpressionReader\MapReader;
use Phel\Transpiler\Domain\Reader\ExpressionReader\MetaReader;
use Phel\Transpiler\Domain\Reader\ExpressionReader\QuoasiquoteReader;
use Phel\Transpiler\Domain\Reader\ExpressionReader\SymbolReader;
use Phel\Transpiler\Domain\Reader\ExpressionReader\VectorReader;
use Phel\Transpiler\Domain\Reader\ExpressionReader\WrapReader;

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
