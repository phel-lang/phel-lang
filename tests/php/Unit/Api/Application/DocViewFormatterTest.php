<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\DocViewFormatter;
use Phel\Lang\TypeFactory;
use Phel\Shared\Api\PhelFunction;
use PHPUnit\Framework\TestCase;

final class DocViewFormatterTest extends TestCase
{
    public function test_it_prints_every_part_in_order(): void
    {
        $fn = new PhelFunction(
            namespace: 'core',
            name: 'old-fn',
            doc: '',
            signatures: ['(old-fn x)', '(old-fn x y)'],
            description: 'Does a thing.',
            meta: [
                'deprecated' => 'use new-fn',
                'example' => "(old-fn 1)\n(old-fn 1 2)",
                'see-also' => TypeFactory::getInstance()->persistentVectorFromArray(['new-fn', 'other']),
            ],
        );

        self::assertSame(
            "core/old-fn\n(old-fn x)\n(old-fn x y)\n\nDoes a thing.\n\nDeprecated: use new-fn\n\n"
            . "Example:\n  (old-fn 1)\n  (old-fn 1 2)\n\nSee also: new-fn, other",
            new DocViewFormatter()->format($fn),
        );
    }

    public function test_missing_parts_leave_no_empty_sections(): void
    {
        $fn = new PhelFunction(namespace: 'string', name: 'f', doc: '', signatures: ['(f)'], description: '');

        self::assertSame("string/f\n(f)", new DocViewFormatter()->format($fn));
    }
}
