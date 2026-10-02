<?php

declare(strict_types=1);

namespace Phel\Lint\Domain;

use Phel\Shared\Lint\LintRuleExplainerInterface;
use Phel\Shared\LintRuleCodes;

use function array_map;
use function array_values;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * The prose behind every lint rule code, read by `phel explain` and by the
 * `fix` field of a lint diagnostic.
 *
 * `LintRuleCatalogTest` fails when a code in {@see LintRuleCodes::allCodes()}
 * has no entry here.
 *
 * @internal
 */
final class LintRuleCatalog implements LintRuleExplainerInterface
{
    private const string CODE_PREFIX = 'phel/';

    /**
     * Accepts the code as `phel lint` prints it, in any case, with or without
     * the `phel/` prefix.
     */
    public static function find(string $input): ?LintRuleExplanation
    {
        $code = strtolower(trim($input));
        if (!str_starts_with($code, self::CODE_PREFIX)) {
            $code = self::CODE_PREFIX . $code;
        }

        return self::entries()[$code] ?? null;
    }

    public function explainRule(string $code): ?array
    {
        return self::find($code)?->toArray();
    }

    public function ruleExplanations(): array
    {
        return array_map(static fn(LintRuleExplanation $explanation): array => $explanation->toArray(), self::all());
    }

    /**
     * @return list<LintRuleExplanation>
     */
    public static function all(): array
    {
        return array_values(self::entries());
    }

    /**
     * @return array<string, LintRuleExplanation>
     */
    private static function entries(): array
    {
        $entries = [];
        foreach (self::explanations() as $explanation) {
            $entries[$explanation->code] = $explanation;
        }

        return $entries;
    }

    /**
     * @return list<LintRuleExplanation>
     */
    private static function explanations(): array
    {
        return [
            new LintRuleExplanation(
                code: LintRuleCodes::UNRESOLVED_SYMBOL,
                title: 'Unresolved symbol',
                summary: 'A symbol names nothing the namespace defines, requires or refers. The lint form of PHEL001.',
                example: '(ns app)' . "\n" . '(prinln "hi")',
                fix: 'Fix the spelling, define the symbol, or require the namespace that defines it.',
            ),
            new LintRuleExplanation(
                code: LintRuleCodes::UNRESOLVED_NAMESPACE,
                title: 'Unresolved namespace',
                summary: 'A (:require ...) names a phel.* namespace that no source, test or vendor directory declares. A clojure.* namespace is checked through its phel.* target.',
                example: '(ns app' . "\n" . '  (:require phel.strng :as s))',
                fix: 'Fix the spelling to the suggested namespace, or install the package that ships it.',
            ),
            new LintRuleExplanation(
                code: LintRuleCodes::ARITY_MISMATCH,
                title: 'Arity mismatch',
                summary: 'A call passes a number of arguments the fn does not accept. The lint form of PHEL002.',
                example: '(defn add [a b] (+ a b))' . "\n" . '(add 1)',
                fix: "Pass the number of arguments one of the fn's arities takes.",
            ),
            new LintRuleExplanation(
                code: LintRuleCodes::UNUSED_BINDING,
                title: 'Unused binding',
                summary: 'A local bound by let, loop or a fn parameter is never read.',
                example: '(let [x 1] 2)',
                fix: 'Remove the binding, or name it with a leading underscore (_x) to say it is unused on purpose.',
            ),
            new LintRuleExplanation(
                code: LintRuleCodes::UNUSED_REQUIRE,
                title: 'Unused require',
                summary: 'A namespace in (:require ...) is never used: neither its alias nor any symbol it refers appears in the file.',
                example: '(ns app' . "\n" . '  (:require phel.string :as s))',
                fix: 'Remove the require, or use the alias or a referred symbol.',
            ),
            new LintRuleExplanation(
                code: LintRuleCodes::UNUSED_IMPORT,
                title: 'Unused import',
                summary: 'A PHP class in (:use ...) is never referenced.',
                example: '(ns app' . "\n" . '  (:use DateTimeImmutable))',
                fix: 'Remove the import, or reference the class.',
            ),
            new LintRuleExplanation(
                code: LintRuleCodes::SHADOWED_BINDING,
                title: 'Shadowed binding',
                summary: 'A local reuses the name of an enclosing local, which hides the outer one.',
                example: '(let [x 1]' . "\n" . '  (let [x 2] x))',
                fix: 'Rename the inner binding.',
            ),
            new LintRuleExplanation(
                code: LintRuleCodes::SHADOWED_CORE_FN,
                title: 'Shadowed core fn',
                summary: 'A local is named after a public phel.core fn, so the fn cannot be called under that name in its scope.',
                example: '(let [inc (fn [x] 99)]' . "\n" . '  (inc 1))',
                fix: 'Rename the binding, or call the core fn as phel.core/inc.',
            ),
            new LintRuleExplanation(
                code: LintRuleCodes::REDUNDANT_DO,
                title: 'Redundant do',
                summary: 'A do has fewer than two body forms, or sits where the body is already an implicit do.',
                example: '(defn f [] (do (println "a") 1))',
                fix: 'Remove the do and keep its body forms.',
            ),
            new LintRuleExplanation(
                code: LintRuleCodes::DUPLICATE_KEY,
                title: 'Duplicate map key',
                summary: 'A map literal names the same key twice. The reader keeps only one of the values.',
                example: '{:a 1 :a 2}',
                fix: 'Remove or rename one of the keys.',
            ),
            new LintRuleExplanation(
                code: LintRuleCodes::DUPLICATE_DEF,
                title: 'Duplicate definition',
                summary: 'A file defines the same top-level name twice. The second definition replaces the first.',
                example: '(defn f [] 1)' . "\n" . '(defn f [] 2)',
                fix: 'Remove or rename one of the definitions. Use (declare f) for a forward reference.',
            ),
            new LintRuleExplanation(
                code: LintRuleCodes::INVALID_DESTRUCTURING,
                title: 'Invalid destructuring',
                summary: 'A binding vector has a name with no value, or & is not followed by exactly one symbol.',
                example: '(let [a 1 b] a)',
                fix: 'Give every name a value, and put & only before the last parameter.',
            ),
            new LintRuleExplanation(
                code: LintRuleCodes::DISCOURAGED_VAR,
                title: 'Discouraged var',
                summary: 'Code uses a definition marked :deprecated.',
                example: '(defn ^{:deprecated "use g"} f [] 1)' . "\n" . '(f)',
                fix: 'Use the replacement the deprecation message names.',
            ),
            new LintRuleExplanation(
                code: LintRuleCodes::COMMENT_STYLE,
                title: 'Comment style',
                summary: 'A comment on a line of its own starts with a single ;, which is reserved for comments after code.',
                example: '; a whole-line comment' . "\n" . '(def x 1)',
                fix: 'Start a whole-line comment with ;;.',
            ),
            new LintRuleExplanation(
                code: LintRuleCodes::INTERNAL_ERROR,
                title: 'Lint rule crashed',
                summary: 'A lint rule threw while checking the file. The finding is about the linter, not about your code.',
                example: '',
                fix: 'Report the message as a bug. Until it is fixed, set the rule the message names to :off in phel-lint.phel.',
            ),
        ];
    }
}
