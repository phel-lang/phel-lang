<?php

declare(strict_types=1);

namespace Phel\Run\Domain;

interface ParenthesesCheckerInterface
{
    public function hasBalancedParentheses(string $input): bool;
}
