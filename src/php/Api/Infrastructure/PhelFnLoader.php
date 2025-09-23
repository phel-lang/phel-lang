<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure;

use Phel;
use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Symbol;
use Phel\Run\RunFacadeInterface;

use function dirname;
use function in_array;
use function sprintf;

final readonly class PhelFnLoader implements PhelFnLoaderInterface
{
    private const array PRIVATE_SYMBOLS = [
        '*file*' => [
            'doc' => '```phel
*file*
```
Returns the path of the current source file.',
            'signature' => '*file*',
            'desc' => 'Returns the path of the current source file.',
        ],
        '*ns*' => [
            'doc' => '```phel
*ns*
```
Returns the namespace in the current scope.',
            'signature' => '*ns*',
            'desc' => 'Returns the namespace in the current scope.',
        ],
        Symbol::NAME_APPLY => [
            'doc' => '```phel
(apply f expr*)
```
Calls the function with the given arguments. The last argument must be a list of values, which are passed as separate arguments, rather than a single list. Apply returns the result of the calling function.',
            'docUrl' => '/documentation/functions-and-recursion/#apply-functions',
            'signature' => '(apply f expr*)',
            'desc' => 'Calls the function with the given arguments. The last argument must be a list of values, which are passed as separate arguments, rather than a single list. Apply returns the result of the calling function.',
        ],
//      Symbol::NAME_CONCAT => [ # this is already in core.phel:97
        Symbol::NAME_DEF => [
            'doc' => '```phel
(def name meta? value)
```
This special form binds a value to a global symbol.',
            'docUrl' => '/documentation/global-and-local-bindings/#definition-def',
            'signature' => '(def name meta? value)',
            'desc' => 'This special form binds a value to a global symbol.',
        ],
        Symbol::NAME_DEF_STRUCT => [
            'doc' => '```phel
(defstruct my-struct [a b c])
```
A Struct is a special kind of Map. It only supports a predefined number of keys and is associated to a global name. The Struct not only defines itself but also a predicate function.',
            'docUrl' => '/documentation/data-structures/#structs',
            'signature' => '(defstruct my-struct [a b c])',
            'desc' => 'A Struct is a special kind of Map. It only supports a predefined number of keys and is associated to a global name. The Struct not only defines itself but also a predicate function.',
        ],
        Symbol::NAME_DEF_EXCEPTION => [
            'doc' => '```phel
(defexception my-ex)
```',
            'docUrl' => '/documentation/exceptions',
            'signature' => '(defexception name)',
            'desc' => 'Defines a new exception.',
        ],
        Symbol::NAME_DO => [
            'doc' => '```phel
(do expr*)
```
Evaluates the expressions in order and returns the value of the last expression. If no expression is given, nil is returned.',
            'docUrl' => '/documentation/control-flow/#statements-do',
            'signature' => '(do expr*)',
            'desc' => 'Evaluates the expressions in order and returns the value of the last expression. If no expression is given, nil is returned.',
        ],
        Symbol::NAME_FN => [
            'doc' => '```phel
(fn [params*] expr*)
```
Defines a function. A function consists of a list of parameters and a list of expression. The value of the last expression is returned as the result of the function. All other expression are only evaluated for side effects. If no expression is given, the function returns nil.',
            'docUrl' => '/documentation/functions-and-recursion/#anonymous-function-fn',
            'signature' => '(fn [params*] expr*)',
            'desc' => 'Defines a function. A function consists of a list of parameters and a list of expression. The value of the last expression is returned as the result of the function. All other expression are only evaluated for side effects. If no expression is given, the function returns nil.',
        ],
        'for' => [
            'docUrl' => '/documentation/control-flow/#for',
        ],
        Symbol::NAME_FOREACH => [
            'doc' => '```phel
(foreach [value valueExpr] expr*)
(foreach [key value valueExpr] expr*)
```
The foreach special form can be used to iterate over all kind of PHP datastructures. The return value of foreach is always nil. The loop special form should be preferred of the foreach special form whenever possible.',
            'signature' => '(foreach [key value valueExpr] expr*)',
            'desc' => 'The foreach special form can be used to iterate over all kind of PHP datastructures. The return value of foreach is always nil. The loop special form should be preferred of the foreach special form whenever possible.',
            'docUrl' => '/documentation/control-flow/#foreach',
        ],
        Symbol::NAME_IF => [
            'doc' => '```phel
(if test then else?)
```
A control flow structure. First evaluates test. If test evaluates to true, only the then form is evaluated and the result is returned. If test evaluates to false only the else form is evaluated and the result is returned. If no else form is given, nil will be returned.

The test evaluates to false if its value is false or equal to nil. Every other value evaluates to true. In sense of PHP this means (test != null && test !== false).',
            'docUrl' => '/documentation/control-flow/#if',
            'signature' => '(if test then else?)',
            'desc' => 'A control flow structure. First evaluates test. If test evaluates to true, only the then form is evaluated and the result is returned. If test evaluates to false only the else form is evaluated and the result is returned. If no else form is given, nil will be returned.',
        ],
        Symbol::NAME_LET => [
            'doc' => '```phel
(let [bindings*] expr*)
```
Creates a new lexical context with assignments defined in bindings. Afterwards the list of expressions is evaluated and the value of the last expression is returned. If no expression is given nil is returned.',
            'docUrl' => '/documentation/global-and-local-bindings/#local-bindings-let',
            'signature' => '(let [bindings*] expr*)',
            'desc' => 'Creates a new lexical context with assignments defined in bindings. Afterwards the list of expressions is evaluated and the value of the last expression is returned. If no expression is given nil is returned.',
        ],
        Symbol::NAME_LOOP => [
            'doc' => '```phel
(loop [bindings*] expr*)
```
Creates a new lexical context with variables defined in bindings and defines a recursion point at the top of the loop.',
            'signature' => '(loop [bindings*] expr*)',
            'desc' => 'Creates a new lexical context with variables defined in bindings and defines a recursion point at the top of the loop.',
        ],
        Symbol::NAME_NS => [
            'doc' => '```phel
(ns name imports*)
```
Defines the namespace for the current file and adds imports to the environment. Imports can either be uses or requires. The keyword `:use` is used to import PHP classes, the keyword `:require` is used to import Phel modules and the keyword `:require-file` is used to load php files.',
            'docUrl' => '/documentation/namespaces/#namespace-ns',
            'signature' => '(ns name imports*)',
            'desc' => 'Defines the namespace for the current file and adds imports to the environment. Imports can either be uses or requires. The keyword :use is used to import PHP classes, the keyword :require is used to import Phel modules and the keyword :require-file is used to load php files.',
        ],
        Symbol::NAME_PHP_ARRAY_GET => [
            'doc' => '```phel
(php/aget arr index)
```
Equivalent to PHP\'s `arr[index] ?? null`.',
            'docUrl' => '/documentation/php-interop/#get-php-array-value',
            'signature' => '(php/aget arr index)',
            'desc' => "Equivalent to PHP's `arr[index] ?? null`.",
        ],
        Symbol::NAME_PHP_ARRAY_GET_IN => [
            'doc' => '```phel
(php/aget-in arr ks)
```
Equivalent to PHP\'s `arr[k1][k2][k...] ?? null`.',
            'docUrl' => '/documentation/php-interop/#get-php-array-value',
            'signature' => '(php/aget-in arr ks)',
            'desc' => "Equivalent to PHP's `arr[k1][k2][k...] ?? null`.",
        ],
        Symbol::NAME_PHP_ARRAY_SET => [
            'doc' => '```phel
(php/aset arr index value)
```
Equivalent to PHP\'s `arr[index] = value`.',
            'docUrl' => '/documentation/php-interop/#set-php-array-value',
            'signature' => '(php/aset arr index value)',
            'desc' => "Equivalent to PHP's `arr[index] = value`.",
        ],
        Symbol::NAME_PHP_ARRAY_SET_IN => [
            'doc' => '```phel
(php/aset-in arr ks value)
```
Equivalent to PHP\'s `arr[k1][k2][k...] = value`.',
            'docUrl' => '/documentation/php-interop/#set-php-array-value',
            'signature' => '(php/aset-in arr ks value)',
            'desc' => "Equivalent to PHP's `arr[k1][k2][k...] = value`.",
        ],
        Symbol::NAME_PHP_ARRAY_PUSH => [
            'doc' => '```phel
(php/apush arr value)
```
Equivalent to PHP\'s `arr[] = value`.',
            'docUrl' => '/documentation/php-interop/#append-php-array-value',
            'signature' => '(php/apush arr value)',
            'desc' => "Equivalent to PHP's arr[] = value.",
        ],
        Symbol::NAME_PHP_ARRAY_PUSH_IN => [
            'doc' => '```phel
(php/apush-in arr ks value)
```
Equivalent to PHP\'s `arr[k1][k2][k...][] = value`.',
            'docUrl' => '/documentation/php-interop/#append-php-array-value',
            'signature' => '(php/apush-in arr ks value)',
            'desc' => "Equivalent to PHP's `arr[k1][k2][k...][] = value`.",
        ],
        Symbol::NAME_PHP_ARRAY_UNSET => [
            'doc' => '```phel
(php/aunset arr index)
```
Equivalent to PHP\'s `unset(arr[index])`.',
            'docUrl' => '/documentation/php-interop/#unset-php-array-value',
            'signature' => '(php/aunset arr index)',
            'desc' => "Equivalent to PHP's `unset(arr[index])`.",
        ],
        Symbol::NAME_PHP_ARRAY_UNSET_IN => [
            'doc' => '```phel
(php/aunset-in arr ks)
```
Equivalent to PHP\'s `unset(arr[k1][k2][k...])`.',
            'docUrl' => '/documentation/php-interop/#unset-php-array-value',
            'signature' => '(php/aunset-in arr ks)',
            'desc' => "Equivalent to PHP's `unset(arr[k1][k2][k...])`.",
        ],
        Symbol::NAME_PHP_NEW => [
            'doc' => '```phel
(php/new expr args*)
```
Evaluates expr and creates a new PHP class using the arguments. The instance of the class is returned.',
            'docUrl' => '/documentation/php-interop/#php-class-instantiation',
            'signature' => '(php/new expr args*)',
            'desc' => 'Evaluates expr and creates a new PHP class using the arguments. The instance of the class is returned.',
        ],
        Symbol::NAME_PHP_OBJECT_CALL => [
            'doc' => '```phel
(php/-> object call*)
(php/:: class call*)
```
Access to an object property or result of chained calls.',
            'docUrl' => '/documentation/php-interop/#php-set-object-properties',
            'signature' => '(php/-> object call*)',
            'desc' => 'Access to an object property or result of chained calls.',
        ],
        Symbol::NAME_PHP_OBJECT_STATIC_CALL => [
            'doc' => '```phel
(php/:: class (methodname expr*))
(php/:: class call*)
```

Calls a static method or property from a PHP class. Both methodname and property must be symbols and cannot be an evaluated value.',
            'docUrl' => '/documentation/php-interop/#php-static-method-and-property-call',
            'signature' => '(php/:: class call*)',
            'desc' => 'Calls a static method or property from a PHP class. Both methodname and property must be symbols and cannot be an evaluated value.',
        ],
        Symbol::NAME_QUOTE => [
            'doc' => '```phel
(NAME_QUOTE)
```',
            'signature' => '(NAME_QUOTE)',
            'desc' => 'NAME_QUOTE description',
        ],
        Symbol::NAME_RECUR => [
            'doc' => '```phel
(recur expr*)
```
Internally recur is implemented as a PHP while loop and therefore prevents the Maximum function nesting level errors.',
            'docUrl' => '/documentation/global-and-local-bindings/#local-bindings-let',
            'signature' => '(recur expr*)',
            'desc' => 'Internally recur is implemented as a PHP while loop and therefore prevents the Maximum function nesting level errors.',
        ],
        Symbol::NAME_UNQUOTE => [
            'doc' => '```phel
(unquote my-sym) # Evaluates to my-sym
,my-sym          # Shorthand for (same as above)
```
Values that should be evaluated in a macro are marked with the unquote function (shorthand `,`).',
            'docUrl' => '/documentation/macros/#quasiquote',
            'signature' => '(unquote my-sym)',
            'desc' => 'Values that should be evaluated in a macro are marked with the unquote function (shorthand ,).',
        ],
        Symbol::NAME_UNQUOTE_SPLICING => [
            'doc' => '```phel
(unquote-splicing my-sym) # Evaluates to my-sym
,@my-sym                  # Shorthand for (same as above)
```
Values that should be evaluated in a macro are marked with the unquote function (shorthand `,@`).',
            'docUrl' => '/documentation/macros/#quasiquote',
            'signature' => '(unquote-splicing my-sym)',
            'desc' => 'Values that should be evaluated in a macro are marked with the unquote function (shorthand ,@).',
        ],
        Symbol::NAME_THROW => [
            'doc' => '```phel
(throw exception)
```
Throw an exception.
See [try-catch](/documentation/control-flow/#try-catch-and-finally).',
            'docUrl' => '',
            'signature' => '(throw exception)',
            'desc' => 'Throw an exception.',
        ],
        'catch' => [
            'doc' => '```phel
(catch exception-type exception-name expr*)
```
Handle exceptions thrown in a `try` block by matching on the provided exception type. The caught exception is bound to `exception-name` while evaluating the expressions.',
            'docUrl' => '/documentation/control-flow/#try-catch-and-finally',
            'signature' => '(catch exception-type exception-name expr*)',
            'desc' => 'Handle exceptions thrown in a `try` block by matching on the provided exception type. The caught exception is bound to exception-name while evaluating the expressions.',
        ],
        Symbol::NAME_TRY => [
            'doc' => '```phel
(try expr* catch-clause* finally-clause?)
```
All expressions are evaluated and if no exception is thrown the value of the last expression is returned. If an exception occurs and a matching catch-clause is provided, its expression is evaluated and the value is returned. If no matching catch-clause can be found the exception is propagated out of the function. Before returning normally or abnormally the optionally finally-clause is evaluated.',
            'docUrl' => '/documentation/control-flow/#try-catch-and-finally',
            'signature' => '(try expr* catch-clause* finally-clause?)',
            'desc' => 'All expressions are evaluated and if no exception is thrown the value of the last expression is returned. If an exception occurs and a matching catch-clause is provided, its expression is evaluated and the value is returned. If no matching catch-clause can be found the exception is propagated out of the function. Before returning normally or abnormally the optionally finally-clause is evaluated.',
        ],
        'finally' => [
            'doc' => '```phel
(finally expr*)
```
Evaluate expressions after the try body and all matching catches have completed. The finally block runs regardless of whether an exception was thrown.',
            'docUrl' => '/documentation/control-flow/#try-catch-and-finally',
            'signature' => '(finally expr*)',
            'desc' => 'Evaluate expressions after the try body and all matching catches have completed. The finally block runs regardless of whether an exception was thrown.',
        ],
        Symbol::NAME_PHP_OBJECT_SET => [
            'doc' => '```phel
(php/oset (php/-> object property) value)
(php/oset (php/:: class property) value)
```
Use `php/oset` to set a value to a class/object property.',
            'docUrl' => '/documentation/php-interop/#php-set-object-properties',
            'signature' => '(php/oset (php/-> object prop) val)',
            'desc' => 'Use `php/oset` to set a value to a class/object property.',
        ],
        Symbol::NAME_LIST => [ # overriding already defined at core.phel:50
            'doc' => '```phel
(list & xs) # \'(& xs)
```
Creates a new list. If no argument is provided, an empty list is created.',
            'docUrl' => '/documentation/data-structures/#lists',
            'signature' => "(list & xs) # '(& xs)",
            'desc' => 'Creates a new list. If no argument is provided, an empty list is created.',
        ],
        Symbol::NAME_VECTOR => [ # overridden already defined at core.phel:54
            'doc' => '```phel
(vector & xs) # [& xs]
```
Creates a new vector. If no argument is provided, an empty vector is created.',
            'docUrl' => '/documentation/data-structures/#vectors',
            'signature' => '(vector & xs) # [& xs]',
            'desc' => 'Creates a new vector. If no argument is provided, an empty vector is created.',
        ],
        Symbol::NAME_MAP => [ # overridden already defined at core.phel:58
            'doc' => '```phel
(hash-map & xs) # {& xs}
```
Creates a new hash map. If no argument is provided, an empty hash map is created. The number of parameters must be even.',
            'docUrl' => '/documentation/data-structures/#maps',
            'signature' => '(hash-map & xs) # {& xs}',
            'desc' => 'Creates a new hash map. If no argument is provided, an empty hash map is created. The number of parameters must be even.',
        ],
        Symbol::NAME_SET_VAR => [
            'doc' => '```phel
(var value)
```
Variables provide a way to manage mutable state. Each variable contains a single value. To create a variable use the var function.',
            'docUrl' => '/documentation/global-and-local-bindings/#variables',
            'signature' => '(var value)',
            'desc' => 'Variables provide a way to manage mutable state. Each variable contains a single value. To create a variable use the var function.',
        ],
        Symbol::NAME_DEF_INTERFACE => [ # overridden
            'doc' => '```phel
(definterface name & fns)
```
An interface in Phel defines an abstract set of functions. It is directly mapped to a PHP interface. An interface can be defined by using the definterface macro.',
            'docUrl' => '/documentation/interfaces/#defining-interfaces',
            'signature' => '(definterface name & fns)',
            'desc' => 'An interface in Phel defines an abstract set of functions. It is directly mapped to a PHP interface. An interface can be defined by using the definterface macro.',
        ],
    ];

    public function __construct(
        private RunFacadeInterface $runFacade,
    ) {
    }

    /**
     * @return array<string,array{
     *     doc?: string,
     *     signature?: string,
     *     desc?: string,
     *     docUrl?: string,
     *     file?: string,
     *     line?: int,
     * }>
     */
    public function getNormalizedNativeSymbols(): array
    {
        return self::PRIVATE_SYMBOLS;
    }

    /**
     * @param list<string> $namespaces
     *
     * @return array<string,PersistentMapInterface>
     */
    public function getNormalizedPhelFunctions(array $namespaces = []): array
    {
        $this->loadAllPhelFunctions($namespaces);
        $containsCoreFunctions = in_array('phel\\core', $namespaces, true);

        /** @var array<string,PersistentMapInterface> $normalizedData */
        $normalizedData = [];
        foreach ($this->getNamespaces() as $ns) {
            if (!$containsCoreFunctions && $ns === 'phel\\core') {
                continue;
            }

            $normalizedNs = str_replace('phel\\', '', $ns);
            $moduleName = $normalizedNs === 'core' ? '' : $normalizedNs . '/';

            foreach (array_keys($this->getDefinitionsInNamespace($ns)) as $fnName) {
                $fullFnName = $moduleName . $fnName;

                $normalizedData[$fullFnName] = $this->getPhelMeta($ns, $fnName);
            }
        }

        ksort($normalizedData);

        return $normalizedData;
    }

    public function loadAllPhelFunctions(array $namespaces): void
    {
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $template = <<<EOF
# Simply require all namespaces that should be documented
(ns phel-internal\doc
  %REQUIRES%
)
EOF;
        $requireNamespaces = '';
        foreach ($namespaces as $ns) {
            $requireNamespaces .= sprintf('(:require %s)', $ns);
        }

        $docPhelContent = str_replace('%REQUIRES%', $requireNamespaces, $template);
        $phelFile = __DIR__ . '/phel/doc.phel';
        file_put_contents($phelFile, $docPhelContent);

        $namespace = $this->runFacade
            ->getNamespaceFromFile($phelFile)
            ->getNamespace();

        $srcDirectories = [
            dirname($phelFile),
            ...$this->runFacade->getAllPhelDirectories(),
        ];

        $namespaceInformation = $this->runFacade->getDependenciesForNamespace(
            $srcDirectories,
            [$namespace, 'phel\\core'],
        );

        foreach ($namespaceInformation as $info) {
            $this->runFacade->evalFile($info);
        }

        unlink($phelFile);
    }

    /**
     * @return list<string>
     */
    private function getNamespaces(): array
    {
        return Phel::getNamespaces();
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefinitionsInNamespace(string $ns): array
    {
        return Phel::getDefinitionInNamespace($ns);
    }

    private function getPhelMeta(string $ns, string $fnName): PersistentMapInterface
    {
        return Phel::getDefinitionMetaData($ns, $fnName)
            ?? Phel::map();
    }
}
