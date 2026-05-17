<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ParserNode;

use Phel\Lang\BigDecimal;
use Phel\Lang\BigInt;
use Phel\Lang\Rational;

/**
 * @extends AbstractAtomNode<BigDecimal|BigInt|float|int|Rational>
 */
final class NumberNode extends AbstractAtomNode {}
