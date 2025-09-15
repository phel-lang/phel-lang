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
use Phel\Compiler\Domain\Reader\ExpressionReader\SetReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\SymbolReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\VectorReader;
use Phel\Compiler\Domain\Reader\ExpressionReader\WrapReader;

interface ExpressionReaderFactoryInterface
{
    public function createSymbolReader(): SymbolReader;

    public function createAtomReader(): AtomReader;

    public function createListReader(Reader $reader): ListReader;

    public function createVectorReader(Reader $reader): VectorReader;

    public function createSetReader(Reader $reader): SetReader;

    public function createMapReader(Reader $reader): MapReader;

    public function createListFnReader(Reader $reader): ListFnReader;

    public function createWrapReader(Reader $reader): WrapReader;

    public function createQuoasiquoteReader(
        Reader $reader,
        QuasiquoteTransformerInterface $quasiquoteTransformer,
    ): QuoasiquoteReader;

    public function createMetaReader(Reader $reader): MetaReader;
}
