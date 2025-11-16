<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Generators\SequenceGenerator as GeneratorsSequenceGenerator;

/**
 * @deprecated Use Phel\Lang\Generators\SequenceGenerator instead
 */
class_alias(GeneratorsSequenceGenerator::class, __NAMESPACE__ . '\\SequenceGenerator');
