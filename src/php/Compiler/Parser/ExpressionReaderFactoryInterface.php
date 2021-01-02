<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Phel\Compiler\Parser\ExpressionReader\AtomReader;
use Phel\Compiler\Parser\ExpressionReader\ListArrayReader;
use Phel\Compiler\Parser\ExpressionReader\ListFnReader;
use Phel\Compiler\Parser\ExpressionReader\ListReader;
use Phel\Compiler\Parser\ExpressionReader\ListTableReader;
use Phel\Compiler\Parser\ExpressionReader\MetaReader;
use Phel\Compiler\Parser\ExpressionReader\QuoasiquoteReader;
use Phel\Compiler\Parser\ExpressionReader\SymbolReader;
use Phel\Compiler\Parser\ExpressionReader\WrapReader;
use Phel\Compiler\Reader;

interface ExpressionReaderFactoryInterface
{
    public function createSymbolReader(): SymbolReader;

    public function createAtomReader(): AtomReader;

    public function createListReader(Reader $reader): ListReader;

    public function createListArrayReader(Reader $reader): ListArrayReader;

    public function createListTableReader(Reader $reader): ListTableReader;

    public function createListFnReader(Reader $reader): ListFnReader;

    public function createWrapReader(Reader $reader): WrapReader;

    public function createQuoasiquoteReader(
        Reader $reader,
        QuasiquoteTransformerInterface $quasiquoteTransformer
    ): QuoasiquoteReader;

    public function createMetaReader(Reader $reader): MetaReader;
}
