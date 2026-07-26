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

/**
 * @internal
 */
interface ExpressionReaderFactoryInterface
{
    public function createSymbolReader(): SymbolReader;

    public function createAtomReader(): AtomReader;

    public function createListReader(ReaderInterface $reader): ListReader;

    public function createVectorReader(ReaderInterface $reader): VectorReader;

    public function createSetReader(ReaderInterface $reader): SetReader;

    public function createMapReader(ReaderInterface $reader): MapReader;

    public function createListFnReader(ReaderInterface $reader): ListFnReader;

    public function createWrapReader(ReaderInterface $reader): WrapReader;

    public function createQuoasiquoteReader(
        ReaderInterface $reader,
        QuasiquoteTransformerInterface $quasiquoteTransformer,
    ): QuoasiquoteReader;

    public function createMetaReader(ReaderInterface $reader): MetaReader;

    public function createTaggedLiteralReader(ReaderInterface $reader): TaggedLiteralReader;
}
