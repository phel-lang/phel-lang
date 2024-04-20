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
        QuasiquoteTransformerInterface $quasiquoteTransformer,
    ): QuoasiquoteReader;

    public function createMetaReader(Reader $reader): MetaReader;
}
