<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\Generator\Builder;

use Phel\Interop\Domain\ReadModel\FunctionToExport;
use Phel\Shared\PhpAttributeRenderer;
use Phel\Shared\ScalarCoercion;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

use function array_map;
use function implode;

final readonly class CompiledPhpMethodBuilder
{
    public function __construct(
        private PhpAttributeRenderer $attributeRenderer = new PhpAttributeRenderer(),
    ) {}

    /**
     * Generates the PHP source of a single wrapper method for an exported Phel function.
     *
     * The compiled Phel function is a class whose `BOUND_TO` constant holds its
     * fully qualified Phel name; reflection on its `__invoke` method provides the
     * parameter list and return type. These are rendered into the method template,
     * replacing the `$ATTRIBUTES$`, `$METHOD_NAME$`, `$ARGS$`, `$RETURN_TYPE$`,
     * `$PHEL_NAMESPACE$` and `$PHEL_FUNCTION_NAME$` tokens, so the generated method
     * delegates to `self::callPhel(...)` at runtime.
     */
    public function build(string $phelNs, FunctionToExport $functionToExport): string
    {
        $ref = new ReflectionClass($functionToExport->fn());
        $boundTo = $ref->hasConstant('BOUND_TO')
            ? ScalarCoercion::toString($ref->getConstant('BOUND_TO'))
            : '';
        $refInvoke = $ref->getMethod('__invoke');

        return str_replace([
            '$ATTRIBUTES$',
            '$METHOD_NAME$',
            '$ARGS$',
            '$RETURN_TYPE$',
            '$PHEL_NAMESPACE$',
            '$PHEL_FUNCTION_NAME$',
        ], [
            $this->buildAttributes($functionToExport),
            $this->buildMethodName($boundTo),
            $this->buildArgs($refInvoke),
            $this->buildReturnType($refInvoke),
            str_replace(['_', '\\'], ['-', '\\\\'], $phelNs),
            $this->buildPhelFunctionName($boundTo),
        ], $this->methodTemplate());
    }

    /**
     * Renders the function's `:php/attr` specs as indented PHP attribute lines
     * placed directly above the generated method, or '' when none are present.
     */
    private function buildAttributes(FunctionToExport $functionToExport): string
    {
        $lines = $this->attributeRenderer->render($functionToExport->attributes());

        return implode('', array_map(
            static fn(string $line): string => '    ' . $line . "\n",
            $lines,
        ));
    }

    private function buildMethodName(string $boundTo): string
    {
        $words = explode('\\', $boundTo);
        $className = array_pop($words);
        $camelCase = $this->underscoreToCamelCase($className);

        return $this->sanitizePhpIdentifier($camelCase);
    }

    private function underscoreToCamelCase(string $string): string
    {
        $str = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));

        return lcfirst($str);
    }

    private function buildArgs(ReflectionMethod $refInvoke): string
    {
        $args = array_map(
            static function (ReflectionParameter $p): string {
                $variadic = $p->isVariadic() ? '...' : '';
                $param = '$' . $p->getName();

                return $variadic . $param;
            },
            $refInvoke->getParameters(),
        );

        return implode(', ', $args);
    }

    private function buildPhelFunctionName(string $boundTo): string
    {
        $suffix = strrchr($boundTo, '\\');

        $functionName = $suffix === false ? $boundTo : substr($suffix, 1);

        return str_replace('_', '-', $functionName);
    }

    private function sanitizePhpIdentifier(string $identifier): string
    {
        $sanitized = (string) preg_replace('/\W/', '_', $identifier);
        $sanitized = (string) preg_replace('/_+/', '_', $sanitized);
        $sanitized = trim($sanitized, '_');

        if ($sanitized === '') {
            $sanitized = 'operator';
        }

        if (is_numeric($sanitized[0])) {
            return '_' . $sanitized;
        }

        return $sanitized;
    }

    private function buildReturnType(ReflectionMethod $refInvoke): string
    {
        $returnType = $refInvoke->getReturnType();

        if ($returnType === null) {
            return ': mixed';
        }

        return ': ' . $returnType->__toString();
    }

    private function methodTemplate(): string
    {
        return <<<'TXT'

$ATTRIBUTES$    public static function $METHOD_NAME$($ARGS$)$RETURN_TYPE$
    {
        return self::callPhel('$PHEL_NAMESPACE$', '$PHEL_FUNCTION_NAME$', $ARGS$);
    }

TXT;
    }
}
