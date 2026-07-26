<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use PHPUnit\Framework\TestCase;

use function preg_match_all;
use function sprintf;

/**
 * Cross-module bindings are keyed by the interface the consumer asks for, so
 * `getProvidedDependency(X::class)` is typed as `X` instead of `mixed`.
 *
 * That is only safe while the *published* id differs from the id the provider
 * body resolves. A facade provider publishes `RunFacadeInterface::class` and
 * resolves `RunFacade::class` — two ids, no cycle. Where a module ships no
 * separate interface, publishing the class the body asks the container for
 * makes the binding re-enter itself and the stack runs out.
 *
 * The failure is nasty enough to be worth pinning: it is not a resolution error
 * but an `Error: Maximum call stack size ... Infinite recursion?` raised from
 * deep inside the container, and only at the moment something first resolves
 * that binding — which may be one integration test, not the module's own.
 */
final class ProviderBindingIdTest extends TestCase
{
    use ScansPhpSourcesTrait;

    public function test_no_provider_publishes_the_id_its_body_resolves(): void
    {
        $selfReferencing = [];

        foreach ($this->phpFilesIn('src/php') as $relative => $contents) {
            if (!str_ends_with($relative, 'Provider.php')) {
                continue;
            }

            preg_match_all(
                '/#\[Provides\((\w+)::class\)\]\s*\n\s*public function \w+\([^)]*\):[^\n]*\n\s*\{\s*\n\s*return ([^\n]+);/',
                $contents,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as $match) {
                [, $publishedId, $body] = $match;

                if (str_contains($body, $publishedId . '::class')) {
                    $selfReferencing[] = sprintf('%s publishes %s::class and resolves it', $relative, $publishedId);
                }
            }
        }

        self::assertSame(
            [],
            $selfReferencing,
            "A provider must not publish the same id its body resolves through the container.\n"
            . 'Key it by the interface the consumer asks for, or by a constant when the module ships no interface.',
        );
    }
}
