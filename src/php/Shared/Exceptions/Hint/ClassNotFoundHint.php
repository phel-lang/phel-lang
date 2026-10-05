<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions\Hint;

use Throwable;

use function preg_match;
use function sprintf;

/**
 * PHP's `Class "X" not found`. Code ported from Clojure calls Java classes
 * that do not exist in PHP, and a method call on a string reads the string as
 * a class name, so the class PHP names is often not one the user wrote.
 */
final class ClassNotFoundHint implements ExceptionHintInterface
{
    private const array JAVA_CLASS_REPLACEMENTS = [
        'Integer' => 'for Integer/parseInt use (parse-long s)',
        'Long' => 'for Long/parseLong use (parse-long s)',
        'Double' => 'for Double/parseDouble use (parse-double s)',
        'Math' => 'for Math/abs use (abs x), and for the rest a PHP function such as (php/floor x) or (php/sqrt x)',
        'System' => 'for System/currentTimeMillis use (php/intval (* 1000 (php/microtime true))), the epoch in milliseconds',
        'Thread' => 'for Thread/sleep use (php/usleep (* ms 1000))',
        'String' => 'for String/valueOf use (str x)',
    ];

    public function appliesTo(Throwable $e): bool
    {
        return $this->extractClass($e->getMessage()) !== null;
    }

    public function hint(Throwable $e): string
    {
        return $this->hintForClass($this->extractClass($e->getMessage()) ?? '');
    }

    /**
     * @internal
     * The Phel replacement for a Java class Clojure code calls, also what
     * `phel lint` names for a static call to it
     */
    public function javaClassHint(string $class): ?string
    {
        if (!isset(self::JAVA_CLASS_REPLACEMENTS[$class])) {
            return null;
        }

        return sprintf('%s is a Java class, not a PHP one: %s.', $class, self::JAVA_CLASS_REPLACEMENTS[$class]);
    }

    private function hintForClass(string $class): string
    {
        $javaClassHint = $this->javaClassHint($class);
        if ($javaClassHint !== null) {
            return $javaClassHint;
        }

        if ($this->looksLikeAString($class)) {
            return sprintf(
                "'%s' was read as a class name, which happens when (.method x) is called on a string. A PHP string has no methods: use phel.string, e.g. (phel.string/upper-case s).",
                $class,
            );
        }

        return sprintf("class '%s' is not loaded. Check its name and (:use ...), and that Composer autoloads it.", $class);
    }

    /**
     * Not a class name at all (`hello world`), or a lowercase name with no
     * namespace (`abc`), which a class is rarely called.
     */
    private function looksLikeAString(string $class): bool
    {
        if (preg_match('/^\\\\?[A-Za-z_][\w\\\\]*$/', $class) !== 1) {
            return true;
        }

        return preg_match('/^[a-z]\w*$/', $class) === 1;
    }

    private function extractClass(string $message): ?string
    {
        if (preg_match('/^Class "([^"]*)" not found/', $message, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
