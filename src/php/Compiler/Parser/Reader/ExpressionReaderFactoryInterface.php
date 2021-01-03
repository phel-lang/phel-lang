<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\Reader;

use Phel\Compiler\Parser\Reader;
use Phel\Compiler\Parser\Reader\ExpressionReader\AtomReader;
use Phel\Compiler\Parser\Reader\ExpressionReader\ListArrayReader;
use Phel\Compiler\Parser\Reader\ExpressionReader\ListFnReader;
use Phel\Compiler\Parser\Reader\ExpressionReader\ListReader;
use Phel\Compiler\Parser\Reader\ExpressionReader\ListTableReader;
use Phel\Compiler\Parser\Reader\ExpressionReader\MetaReader;
use Phel\Compiler\Parser\Reader\ExpressionReader\QuoasiquoteReader;
use Phel\Compiler\Parser\Reader\ExpressionReader\SymbolReader;
use Phel\Compiler\Parser\Reader\ExpressionReader\WrapReader;

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
