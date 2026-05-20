<?php

/**
 * Composer post-install/post-update hook that patches a PHP 8.5
 * incompatibility in the vendored Psalm release: `TLiteralFloat::getKey()`
 * and `TLiteralFloat::getId()` coerce `$this->value` to a string via
 * concatenation. When the literal float happens to be `NAN` (e.g. analysis
 * of `##NaN` constants), PHP 8.5 raises an Error ("unexpected NAN value was
 * coerced to string") and crashes the analyser.
 *
 * The fix is local and idempotent: wrap the coercion in an `is_nan()` /
 * `is_infinite()` branch so NAN and Inf serialise to a readable token. The
 * patch is reapplied on every Composer run so a fresh vendor/ install will
 * not regress.
 */

declare(strict_types=1);

$file = __DIR__ . '/../../vendor/vimeo/psalm/src/Psalm/Type/Atomic/TLiteralFloat.php';

if (!is_file($file)) {
    echo "patch-psalm: target file not present, skipping\n";
    exit(0);
}

$source = file_get_contents($file);
if ($source === false) {
    fwrite(STDERR, "patch-psalm: unable to read {$file}\n");
    exit(1);
}

if (str_contains($source, 'self::stringifyFloat(')) {
    echo "patch-psalm: already patched\n";
    exit(0);
}

$helperBlock = <<<'PHP'

    /**
     * NAN and Inf cannot be coerced to a string in PHP 8.5+, so this helper
     * returns a stable, readable token for them.
     */
    private static function stringifyFloat(float $value): string
    {
        if (is_nan($value)) {
            return 'NAN';
        }

        if (is_infinite($value)) {
            return $value > 0 ? 'INF' : '-INF';
        }

        return (string) $value;
    }
}
PHP;

$patched = str_replace(
    [
        "return 'float(' . \$this->value . ')';",
    ],
    [
        "return 'float(' . self::stringifyFloat(\$this->value) . ')';",
    ],
    $source,
);

// Replace the final closing brace of the class with the closing brace plus
// the helper. The class is `final`, so there is exactly one matching brace
// at the end of the file.
$patched = preg_replace('/}\s*$/', $helperBlock . "\n", $patched, 1);

if ($patched === $source) {
    fwrite(STDERR, "patch-psalm: nothing to replace, file shape unexpected\n");
    exit(1);
}

if (file_put_contents($file, $patched) === false) {
    fwrite(STDERR, "patch-psalm: failed to write {$file}\n");
    exit(1);
}

echo "patch-psalm: patched TLiteralFloat for PHP 8.5 compatibility\n";
