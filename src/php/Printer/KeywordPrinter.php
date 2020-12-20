<?php

declare(strict_types=1);

namespace Phel\Printer;

use Phel\Lang\Keyword;

final class KeywordPrinter
{
    public function print(Keyword $form): string
    {
        return ':' . $form->getName();
    }
}
