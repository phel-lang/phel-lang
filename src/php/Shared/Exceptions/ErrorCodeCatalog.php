<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions;

use function ltrim;
use function str_pad;
use function strtoupper;
use function trim;

use const STR_PAD_LEFT;

/**
 * The prose behind every {@see ErrorCode}: one {@see ErrorCodeExplanation} per
 * case, and the single source both `phel explain` and the generated pages under
 * `docs/errors/` read.
 *
 * A code with no entry here is a code nobody can look up, so
 * `ErrorCodeInventoryTest` fails when one is missing.
 */
final class ErrorCodeCatalog
{
    private const string CODE_PREFIX = 'PHEL';

    private const int CODE_DIGITS = 3;

    /**
     * Accepts what a user actually types after reading `[PHEL008]` off their
     * terminal: the code itself, in any case, with or without the prefix, and
     * with or without the leading zeroes.
     */
    public static function find(string $input): ?ErrorCodeExplanation
    {
        $code = ErrorCode::tryFrom(self::normalize($input));

        return $code instanceof ErrorCode ? self::explain($code) : null;
    }

    public static function explain(ErrorCode $code): ErrorCodeExplanation
    {
        return self::entries()[$code->value];
    }

    /**
     * @return list<ErrorCodeExplanation> in the order the enum declares, so the
     *                                    generated pages follow the ranges
     */
    public static function all(): array
    {
        return array_values(self::entries());
    }

    private static function normalize(string $input): string
    {
        $digits = ltrim(strtoupper(trim($input)), self::CODE_PREFIX);

        return self::CODE_PREFIX . str_pad($digits, self::CODE_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, ErrorCodeExplanation> keyed by code value
     */
    private static function entries(): array
    {
        static $entries = null;

        if ($entries === null) {
            $entries = [];
            foreach (self::declarations() as $explanation) {
                $entries[$explanation->code->value] = $explanation;
            }
        }

        return $entries;
    }

    /**
     * @return list<ErrorCodeExplanation>
     */
    private static function declarations(): array
    {
        return [
            new ErrorCodeExplanation(
                code: ErrorCode::UNDEFINED_SYMBOL,
                title: 'Undefined symbol',
                summary: 'The analyzer reached a symbol that is bound nowhere: not in the current namespace, not in a required namespace, and not in a local binding.',
                example: '(undefined-fn 1 2)',
                fix: 'Check the spelling, require the namespace that defines it, or define it before the call.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::ARITY_ERROR,
                title: 'Wrong number of arguments',
                summary: 'The call does not match the arity the analyzer knows. For a function that means a global definition the compiler has already seen; a special form given too few arguments reports here too, and names the shape it wanted.',
                example: '(map)',
                fix: 'Pass the number of arguments the function declares.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::TYPE_ERROR,
                title: 'Wrong argument type',
                summary: 'A special form got the wrong kind of value in a fixed position. The analyzer checks these shapes before any code runs.',
                example: '(def 1 2)',
                fix: 'Put the type the form expects in that position, here a symbol.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::DUPLICATE_DEFINITION,
                title: 'Duplicate definition',
                summary: 'The same name is defined twice in one namespace. The analyzer reports the second definition and names the line of the first. The REPL and `phel eval` stand this guard down, so it only fires on a file.',
                example: "(ns app.core)\n(def a 1)\n(def a 2)",
                fix: 'Rename one of the two definitions, or delete the one you no longer need.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::MACRO_EXPANSION_ERROR,
                title: 'Error while expanding a macro',
                summary: 'A macro threw while the analyzer was expanding a call to it. The report gives the failing call, the cause, and where the macro is defined.',
                example: "(defmacro boom [] (/ 1 0))\n(boom)",
                fix: 'Fix the macro body, or the arguments the call passes to it.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::INLINE_EXPANSION_ERROR,
                title: 'Error while expanding an inline function',
                summary: 'The `:inline` function on a definition threw while the analyzer was expanding a call to it. Same failure as a macro expansion, on the inline path.',
                example: "(defn ^{:inline (fn [x] (/ 1 0))} f [x] x)\n(f 1)",
                fix: 'Fix the `:inline` function so it returns a form for every call it accepts.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::INVALID_SPECIAL_FORM,
                title: 'Invalid special form',
                summary: 'The fallback code for an analyzer error that carries no more specific one, such as a special form given the wrong number of arguments. Editor diagnostics report it under this code; the terminal prints the same message with no code in front.',
                example: '(if)',
                fix: 'Read the message: it names the form and what it expected there.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::BINDING_ERROR,
                title: 'Invalid binding form',
                summary: 'A `let`, `loop` or destructuring binding has the wrong shape. The binding list must be a vector of an even number of forms, and every bound name must be an unqualified symbol.',
                example: '(let (a 1) a)',
                fix: 'Write the bindings as a vector of name and value pairs.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::INTERFACE_ERROR,
                title: 'Invalid interface implementation',
                summary: 'A `defstruct` or `defenum` names an interface the analyzer cannot resolve, or leaves one of its methods unimplemented.',
                example: '(defstruct point [x y] Unknown)',
                fix: 'Import the interface, spell the name the way the `ns` form does, and implement every method it declares.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::RECUR_ERROR,
                title: 'Invalid recur',
                summary: '`recur` was used outside the tail of a `loop` or `fn`, or with an argument count that does not match the recursion point.',
                example: '(recur 1)',
                fix: 'Move the `recur` into the tail of a `loop` or `fn` and pass one argument per binding.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::NOT_CALLABLE,
                title: 'Value in call position is not callable',
                summary: 'A literal number, string, boolean or `nil` sits at the head of a list. The analyzer rejects it instead of letting PHP fail at runtime.',
                example: '(1 2)',
                fix: 'Drop the parentheses, or put a function at the head of the list.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::UNTERMINATED_LIST,
                title: 'Unterminated list',
                summary: 'The parser reached the end of the file with a `(` still open. The reported line is where the list was opened, not where the file ran out.',
                example: '(inc 1',
                fix: 'Add the closing `)`, or run `phel balance` to locate the missing one.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::UNTERMINATED_VECTOR,
                title: 'Unterminated vector',
                summary: 'The parser reached the end of the file with a `[` still open. A `)` where the `]` belongs reports the same code.',
                example: '[1 2',
                fix: 'Add the closing `]`.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::UNTERMINATED_MAP,
                title: 'Unterminated map',
                summary: 'The parser reached the end of the file with a `{` still open.',
                example: '{:a 1',
                fix: 'Add the closing `}`.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::UNTERMINATED_TABLE,
                title: 'Unterminated set',
                summary: 'The parser reached the end of the file with a `#{` set literal still open. The code is spelled `UNTERMINATED_TABLE` from when sets were written as tables.',
                example: '#{1 2',
                fix: 'Add the closing `}`.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::UNEXPECTED_TOKEN,
                title: 'Unexpected token',
                summary: "The parser found a closing delimiter with no open form to close, or a reader prefix such as `'` or `^` with no form after it.",
                example: '(inc 1))',
                fix: 'Delete the extra delimiter, or write the form the prefix is waiting for.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::PARSER_ERROR,
                title: 'Parser error',
                summary: 'The fallback code for a parser error that carries no more specific one, such as a keyword alias the parser cannot resolve. Editor diagnostics report it under this code; the terminal prints the same message with no code in front.',
                example: '::nope/foo',
                fix: 'Read the message: it names the token the parser rejected.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::INVALID_SPLICE,
                title: 'Unquote-splicing outside a collection',
                summary: 'The reader found `~@` in a quasiquote with no collection to splice into. Splicing is only defined inside a list, vector, map or set.',
                example: '`~@[1 2]',
                fix: 'Wrap the splice in a collection, or use `~` to unquote a single value.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::READER_ERROR,
                title: 'Reader error',
                summary: 'The fallback code for a reader error that carries no more specific one: an odd-length map literal, metadata on a value that cannot hold it, an unknown tagged literal. Editor diagnostics report it under this code; the terminal prints the same message with no code in front.',
                example: '{:a}',
                fix: 'Read the message: it names the form the reader rejected.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::UNTERMINATED_STRING,
                title: 'Unterminated string',
                summary: 'A `"` opens a string that no later `"` closes. The lexer hands the rest to the atom rule, and the parser reports it at the opening quote.',
                example: '"abc',
                fix: 'Add the closing `"`, or escape the quote you meant to keep as `\\"`.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::LEXER_ERROR,
                title: 'Lexer error',
                summary: 'No token rule matches the source at that position. A bare `#` is the usual cause: every `#` form needs the character that follows it.',
                example: '(inc #)',
                fix: 'Delete the stray character, or complete the reader form it starts.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::RUNTIME_NOT_CALLABLE,
                title: 'Value is not callable at runtime',
                summary: 'A call reached a value that is not a function, and the analyzer could not see it coming. It fires when the head of the list is a name or an expression rather than a literal.',
                example: "(def x 1)\n(x 2)",
                fix: 'Call a function, or read the value with an accessor such as `get`, `nth` or `first`.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::RUNTIME_ARITY_ERROR,
                title: 'Wrong number of arguments at runtime',
                summary: 'A function was called with an argument count its signature does not accept. PHP raises it when the callee is a value the analyzer has no arity for, such as a local closure.',
                example: '((fn [a] a))',
                fix: 'Pass one argument per declared parameter.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::RUNTIME_TYPE_ERROR,
                title: 'Wrong type at runtime',
                summary: 'A PHP function or method got an argument of a type it does not accept. Most of these come from `php/` interop calls.',
                example: '(php/array_sum "x")',
                fix: 'Convert the value to the type the callee declares before passing it.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::INDEX_OUT_OF_BOUNDS,
                title: 'Index out of bounds',
                summary: 'An indexed read asked for a position the collection does not have. `nth` throws here; `get` returns `nil` instead.',
                example: '(nth [1 2] 9)',
                fix: 'Check the index against `(count coll)`, or use `get` with a default.',
            ),
            new ErrorCodeExplanation(
                code: ErrorCode::DIVISION_BY_ZERO,
                title: 'Division by zero',
                summary: 'A division or remainder had zero on the right. `/`, `%`, `rem` and `php/intdiv` all raise it.',
                example: '(/ 1 0)',
                fix: 'Guard the divisor before dividing.',
            ),
        ];
    }
}
