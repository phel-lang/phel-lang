<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader;

use Phel\Compiler\Reader\ExpressionReader\AtomReader;
use Phel\Compiler\Reader\ExpressionReader\ListArrayReader;
use Phel\Compiler\Reader\ExpressionReader\ListFnReader;
use Phel\Compiler\Reader\ExpressionReader\ListReader;
use Phel\Compiler\Reader\ExpressionReader\ListTableReader;
use Phel\Compiler\Reader\ExpressionReader\MetaReader;
use Phel\Compiler\Reader\ExpressionReader\QuoasiquoteReader;
use Phel\Compiler\Reader\ExpressionReader\SymbolReader;
use Phel\Compiler\Reader\ExpressionReader\WrapReader;

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

    public function createListArrayReader(Reader $reader): ListArrayReader
    {
        return new ListArrayReader($reader);
    }

    public function createListTableReader(Reader $reader): ListTableReader
    {
        return new ListTableReader($reader);
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
        QuasiquoteTransformerInterface $quasiquoteTransformer
    ): QuoasiquoteReader {
        return new QuoasiquoteReader($reader, $quasiquoteTransformer);
    }

    public function createMetaReader(Reader $reader): MetaReader
    {
        return new MetaReader($reader);
    }
}
