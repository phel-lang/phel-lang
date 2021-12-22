<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader;

use Phel\Compiler\Reader\ExpressionReader\AtomReader;
use Phel\Compiler\Reader\ExpressionReader\ListFnReader;
use Phel\Compiler\Reader\ExpressionReader\ListReader;
use Phel\Compiler\Reader\ExpressionReader\MapReader;
use Phel\Compiler\Reader\ExpressionReader\MetaReader;
use Phel\Compiler\Reader\ExpressionReader\QuoasiquoteReader;
use Phel\Compiler\Reader\ExpressionReader\SymbolReader;
use Phel\Compiler\Reader\ExpressionReader\VectorReader;
use Phel\Compiler\Reader\ExpressionReader\WrapReader;

interface ExpressionReaderFactoryInterface
{
    public function createSymbolReader(): SymbolReader;

    public function createAtomReader(): AtomReader;

    public function createListReader(Reader $reader): ListReader;

    public function createVectorReader(Reader $reader): VectorReader;

    public function createMapReader(Reader $reader): MapReader;

    public function createListFnReader(Reader $reader): ListFnReader;

    public function createWrapReader(Reader $reader): WrapReader;

    public function createQuoasiquoteReader(
        Reader $reader,
        QuasiquoteTransformerInterface $quasiquoteTransformer
    ): QuoasiquoteReader;

    public function createMetaReader(Reader $reader): MetaReader;
}
