<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ParserNode;

use Phel\Lang\BigInteger;
use Phel\Lang\Rational;

/**
 * @extends AbstractAtomNode<BigInteger|float|int|Rational>
 */
final class NumberNode extends AbstractAtomNode {}
