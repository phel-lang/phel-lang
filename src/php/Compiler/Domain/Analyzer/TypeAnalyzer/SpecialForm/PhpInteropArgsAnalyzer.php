<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNamedArgNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Keyword;
use Phel\Lang\TypeInterface;

use function array_slice;
use function count;
use function sprintf;

/**
 * Analyzes the argument forms of a `php/new`/`php/->`/`php/::` call,
 * supporting PHP 8 named arguments after a `:&` marker:
 *
 *     (php/new \App\Mailer "smtp" :& :port 25 :secure true)
 *     ; => new \App\Mailer("smtp", port: 25, secure: true)
 *
 * Positional args come first; everything after `:&` must be `:keyword value`
 * pairs, each emitted as a PHP named argument (see {@see PhpNamedArgNode}).
 */
final readonly class PhpInteropArgsAnalyzer
{
    /** The marker keyword (`:&`) that switches the arg list to named mode. */
    private const string NAMED_ARGS_MARKER = '&';

    public function __construct(
        private AnalyzerInterface $analyzer,
    ) {}

    /**
     * @param list<mixed> $forms argument forms without the callee
     *
     * @return list<AbstractNode>
     */
    public function analyze(array $forms, NodeEnvironmentInterface $env, string $context, TypeInterface $location): array
    {
        $args = [];
        $count = count($forms);

        for ($i = 0; $i < $count; ++$i) {
            $form = $forms[$i];

            if ($this->isNamedArgsMarker($form)) {
                $namedForms = array_slice($forms, $i + 1);
                foreach ($this->analyzeNamedArgs($namedForms, $env, $context, $location) as $namedArg) {
                    $args[] = $namedArg;
                }

                return $args;
            }

            $args[] = $this->analyzer->analyze($form, $env);
        }

        return $args;
    }

    /**
     * @param list<mixed> $forms the forms following the `:&` marker
     *
     * @return list<PhpNamedArgNode>
     */
    private function analyzeNamedArgs(array $forms, NodeEnvironmentInterface $env, string $context, TypeInterface $location): array
    {
        $count = count($forms);
        if ($count === 0) {
            throw AnalyzerException::withLocation(
                sprintf("':&' in '%s' must be followed by :key value pairs", $context),
                $location,
            );
        }

        $named = [];
        for ($i = 0; $i < $count; $i += 2) {
            $key = $forms[$i];
            if (!$key instanceof Keyword || $key->getNamespace() !== null) {
                throw AnalyzerException::withLocation(
                    sprintf("Named arguments after ':&' in '%s' must be :key value pairs", $context),
                    $key instanceof TypeInterface ? $key : $location,
                );
            }

            if ($i + 1 >= $count) {
                throw AnalyzerException::withLocation(
                    sprintf("Missing value for named argument ':%s' in '%s'", $key->getName(), $context),
                    $key,
                );
            }

            $named[] = new PhpNamedArgNode(
                $env,
                $key->getName(),
                $this->analyzer->analyze($forms[$i + 1], $env),
                $key->getStartLocation(),
            );
        }

        return $named;
    }

    private function isNamedArgsMarker(mixed $form): bool
    {
        return $form instanceof Keyword
            && $form->getNamespace() === null
            && $form->getName() === self::NAMED_ARGS_MARKER;
    }
}
