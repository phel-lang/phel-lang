<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure;

use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Run\RunFacadeInterface;

use function dirname;
use function in_array;
use function sprintf;

final readonly class PhelFnLoader implements PhelFnLoaderInterface
{
    private const PRIVATE_SYMBOLS = [
        Symbol::NAME_APPLY => [
            'doc' => '```phel
(apply f expr*)
```
Calls the function with the given arguments. The last argument must be a list of values, which are passed as separate arguments, rather than a single list. Apply returns the result of the calling function.',
            'url' => '/documentation/functions-and-recursion/#apply-functions',
            'fnSignature' => '(apply f expr*)',
            'desc' => 'Calls the function with the given arguments. The last argument must be a list of values, which are passed as separate arguments, rather than a single list. Apply returns the result of the calling function.',
        ],
//      Symbol::NAME_CONCAT => [ # this is already in core.phel:97
        Symbol::NAME_DEF => [
            'doc' => '```phel
(def name meta? value)
```
This special form binds a value to a global symbol.',
            'url' => '/documentation/global-and-local-bindings/#definition-def',
            'fnSignature' => '(def name meta? value)',
            'desc' => 'This special form binds a value to a global symbol.',
        ],
        Symbol::NAME_DEF_STRUCT => [
            'doc' => '```phel
(defstruct my-struct [a b c])
```
A Struct is a special kind of Map. It only supports a predefined number of keys and is associated to a global name. The Struct not only defines itself but also a predicate function.',
            'url' => '/documentation/data-structures/#structs',
            'fnSignature' => '(defstruct my-struct [a b c])',
            'desc' => 'A Struct is a special kind of Map. It only supports a predefined number of keys and is associated to a global name. The Struct not only defines itself but also a predicate function.',
        ],
        Symbol::NAME_DO => [
            'doc' => '```phel
(do expr*)
```
Evaluates the expressions in order and returns the value of the last expression. If no expression is given, nil is returned.',
            'url' => '/documentation/control-flow/#statements-do',
            'fnSignature' => '(do expr*)',
            'desc' => 'Evaluates the expressions in order and returns the value of the last expression. If no expression is given, nil is returned.',
        ],
        Symbol::NAME_FN => [
            'doc' => '```phel
(fn [params*] expr*)
```
Defines a function. A function consists of a list of parameters and a list of expression. The value of the last expression is returned as the result of the function. All other expression are only evaluated for side effects. If no expression is given, the function returns nil.',
            'url' => '/documentation/functions-and-recursion/#anonymous-function-fn',
            'fnSignature' => '(fn [params*] expr*)',
            'desc' => 'Defines a function. A function consists of a list of parameters and a list of expression. The value of the last expression is returned as the result of the function. All other expression are only evaluated for side effects. If no expression is given, the function returns nil.',
        ],
        'for' => [
            'url' => '/documentation/control-flow/#for',
        ],
        Symbol::NAME_FOREACH => [
            'doc' => '```phel
(foreach [value valueExpr] expr*)
(foreach [key value valueExpr] expr*)
```
The foreach special form can be used to iterate over all kind of PHP datastructures. The return value of foreach is always nil. The loop special form should be preferred of the foreach special form whenever possible.',
            'fnSignature' => '(foreach [key value valueExpr] expr*)',
            'desc' => 'The foreach special form can be used to iterate over all kind of PHP datastructures. The return value of foreach is always nil. The loop special form should be preferred of the foreach special form whenever possible.',
            'url' => '/documentation/control-flow/#foreach',
        ],
        Symbol::NAME_IF => [
            'doc' => '```phel
(if test then else?)
```
A control flow structure. First evaluates test. If test evaluates to true, only the then form is evaluated and the result is returned. If test evaluates to false only the else form is evaluated and the result is returned. If no else form is given, nil will be returned.

The test evaluates to false if its value is false or equal to nil. Every other value evaluates to true. In sense of PHP this means (test != null && test !== false).',
            'url' => '/documentation/control-flow/#if',
            'fnSignature' => '(if test then else?)',
            'desc' => 'A control flow structure. First evaluates test. If test evaluates to true, only the then form is evaluated and the result is returned. If test evaluates to false only the else form is evaluated and the result is returned. If no else form is given, nil will be returned.',
        ],
        Symbol::NAME_LET => [
            'doc' => '```phel
(let [bindings*] expr*)
```
Creates a new lexical context with assignments defined in bindings. Afterwards the list of expressions is evaluated and the value of the last expression is returned. If no expression is given nil is returned.',
            'url' => '/documentation/global-and-local-bindings/#local-bindings-let',
            'fnSignature' => '(let [bindings*] expr*)',
            'desc' => 'Creates a new lexical context with assignments defined in bindings. Afterwards the list of expressions is evaluated and the value of the last expression is returned. If no expression is given nil is returned.',
        ],
        Symbol::NAME_LOOP => [
            'doc' => '```phel
(loop [bindings*] expr*)
```
Creates a new lexical context with variables defined in bindings and defines a recursion point at the top of the loop.',
            'fnSignature' => '(loop [bindings*] expr*)',
            'desc' => 'Creates a new lexical context with variables defined in bindings and defines a recursion point at the top of the loop.',
        ],
        Symbol::NAME_NS => [
            'doc' => '```phel
(ns name imports*)
```
Defines the namespace for the current file and adds imports to the environment. Imports can either be uses or requires. The keyword `:use` is used to import PHP classes, the keyword `:require` is used to import Phel modules and the keyword `:require-file` is used to load php files.',
            'url' => '/documentation/namespaces/#namespace-ns',
            'fnSignature' => '(ns name imports*)',
            'desc' => 'Defines the namespace for the current file and adds imports to the environment. Imports can either be uses or requires. The keyword :use is used to import PHP classes, the keyword :require is used to import Phel modules and the keyword :require-file is used to load php files.',
        ],
        Symbol::NAME_PHP_ARRAY_GET => [
            'doc' => '```phel
(php/aget arr index)
```
Equivalent to PHP\'s `arr[index] ?? null`.',
            'url' => '/documentation/php-interop/#get-php-array-value',
            'fnSignature' => '(php/aget arr index)',
            'desc' => "Equivalent to PHP's `arr[index] ?? null`.",
        ],
        Symbol::NAME_PHP_ARRAY_SET => [
            'doc' => '```phel
(php/aset arr index value)
```
Equivalent to PHP\'s `arr[index] = value`.',
            'url' => '/documentation/php-interop/#set-php-array-value',
            'fnSignature' => '(php/aset arr index value)',
            'desc' => "Equivalent to PHP's `arr[index] = value`.",
        ],
        Symbol::NAME_PHP_ARRAY_PUSH => [
            'doc' => '```phel
(php/apush arr value)
```
Equivalent to PHP\'s `arr[] = value`.',
            'url' => '/documentation/php-interop/#append-php-array-value',
            'fnSignature' => '(php/apush arr value)',
            'desc' => "Equivalent to PHP's arr[] = value.",
        ],
        Symbol::NAME_PHP_ARRAY_UNSET => [
            'doc' => '```phel
(php/aunset arr index)
```
Equivalent to PHP\'s `unset(arr[index])`.',
            'url' => '/documentation/php-interop/#unset-php-array-value',
            'fnSignature' => '(php/aunset arr index)',
            'desc' => "Equivalent to PHP's `unset(arr[index])`.",
        ],
        Symbol::NAME_PHP_NEW => [
            'doc' => '```phel
(php/new expr args*)
```
Evaluates expr and creates a new PHP class using the arguments. The instance of the class is returned.',
            'url' => '/documentation/php-interop/#php-class-instantiation',
            'fnSignature' => '(php/new expr args*)',
            'desc' => 'Evaluates expr and creates a new PHP class using the arguments. The instance of the class is returned.',
        ],
        Symbol::NAME_PHP_OBJECT_CALL => [
            'doc' => '```phel
(php/-> object property)
(php/:: class property)
```
Access to an object property or static attribute.',
            'url' => '/documentation/php-interop/#php-set-object-properties',
            'fnSignature' => '(php/-> object property)',
            'desc' => 'Access to an object property or static attribute.',
        ],
        Symbol::NAME_PHP_OBJECT_STATIC_CALL => [
            'doc' => '```phel
(php/:: class (methodname expr*))
(php/:: class property)
```

Calls a static method or property from a PHP class. Both methodname and property must be symbols and cannot be an evaluated value.',
            'url' => '/documentation/php-interop/#php-static-method-and-property-call',
            'fnSignature' => '(php/:: class (methodname expr*))',
            'desc' => 'Calls a static method or property from a PHP class. Both methodname and property must be symbols and cannot be an evaluated value.',
        ],
        Symbol::NAME_QUOTE => [
            'doc' => '```phel
(NAME_QUOTE)
```',
            'fnSignature' => '(NAME_QUOTE)',
            'desc' => 'NAME_QUOTE description',
        ],
        Symbol::NAME_RECUR => [
            'doc' => '```phel
(recur expr*)
Internally recur is implemented as a PHP while loop and therefore prevents the Maximum function nesting level errors..
```',
            'url' => '/documentation/global-and-local-bindings/#local-bindings-let',
            'fnSignature' => '(recur expr*)',
            'desc' => 'Internally recur is implemented as a PHP while loop and therefore prevents the Maximum function nesting level errors.',
        ],
        Symbol::NAME_UNQUOTE => [
            'doc' => '```phel
(unquote my-sym) # Evaluates to my-sym
,my-sym          # Shorthand for (same as above)
```
Values that should be evaluated in a macro are marked with the unquote function (shorthand `,`).',
            'url' => '/documentation/macros/#quasiquote',
            'fnSignature' => '(unquote my-sym)',
            'desc' => 'Values that should be evaluated in a macro are marked with the unquote function (shorthand ,).',
        ],
        Symbol::NAME_UNQUOTE_SPLICING => [
            'doc' => '```phel
(unquote-splicing my-sym) # Evaluates to my-sym
,@my-sym                  # Shorthand for (same as above)
```
Values that should be evaluated in a macro are marked with the unquote function (shorthand `,@`).',
            'url' => '/documentation/macros/#quasiquote',
            'fnSignature' => '(unquote-splicing my-sym)',
            'desc' => 'Values that should be evaluated in a macro are marked with the unquote function (shorthand ,@).',
        ],
        Symbol::NAME_THROW => [
            'doc' => '```phel
(throw exception)
```
Throw an exception.
See [try-catch](/documentation/control-flow/#try-catch-and-finally).',
            'url' => '',
            'fnSignature' => '(throw exception)',
            'desc' => 'Throw an exception.',
        ],
        Symbol::NAME_TRY => [
            'doc' => '```phel
(try expr* catch-clause* finally-clause?)
```
All expressions are evaluated and if no exception is thrown the value of the last expression is returned. If an exception occurs and a matching catch-clause is provided, its expression is evaluated and the value is returned. If no matching catch-clause can be found the exception is propagated out of the function. Before returning normally or abnormally the optionally finally-clause is evaluated.',
            'url' => '/documentation/control-flow/#try-catch-and-finally',
            'fnSignature' => '(try expr* catch-clause* finally-clause?)',
            'desc' => 'All expressions are evaluated and if no exception is thrown the value of the last expression is returned. If an exception occurs and a matching catch-clause is provided, its expression is evaluated and the value is returned. If no matching catch-clause can be found the exception is propagated out of the function. Before returning normally or abnormally the optionally finally-clause is evaluated.',
        ],
        Symbol::NAME_PHP_OBJECT_SET => [
            'doc' => '```phel
(php/oset (php/-> object property) value)
(php/oset (php/:: class property) value)
```
Use `php/oset` to set a value to a class/object property.',
            'url' => '/documentation/php-interop/#php-set-object-properties',
            'fnSignature' => '(php/oset (php/-> object prop) val)',
            'desc' => 'Use `php/oset` to set a value to a class/object property.',
        ],
        Symbol::NAME_LIST => [ # overriding already defined at core.phel:50
            'doc' => '```phel
(list & xs) # \'(& xs)
```
Creates a new list. If no argument is provided, an empty list is created.',
            'url' => '/documentation/data-structures/#lists',
            'fnSignature' => "(list & xs) # '(& xs)",
            'desc' => 'Creates a new list. If no argument is provided, an empty list is created.',
        ],
        Symbol::NAME_VECTOR => [ # overridden already defined at core.phel:54
            'doc' => '```phel
(vector & xs) # [& xs]
```
Creates a new vector. If no argument is provided, an empty vector is created.',
            'url' => '/documentation/data-structures/#vectors',
            'fnSignature' => '(vector & xs) # [& xs]',
            'desc' => 'Creates a new vector. If no argument is provided, an empty vector is created.',
        ],
        Symbol::NAME_MAP => [ # overridden already defined at core.phel:58
            'doc' => '```phel
(hash-map & xs) # {& xs}
```
Creates a new hash map. If no argument is provided, an empty hash map is created. The number of parameters must be even.',
            'url' => '/documentation/data-structures/#maps',
            'fnSignature' => '(hash-map & xs) # {& xs}',
            'desc' => 'Creates a new hash map. If no argument is provided, an empty hash map is created. The number of parameters must be even.',
        ],
        Symbol::NAME_SET_VAR => [
            'doc' => '```phel
(var value)
```
Variables provide a way to manage mutable state. Each variable contains a single value. To create a variable use the var function.',
            'url' => '/documentation/global-and-local-bindings/#variables',
            'fnSignature' => '(var value)',
            'desc' => 'Variables provide a way to manage mutable state. Each variable contains a single value. To create a variable use the var function.',
        ],
        Symbol::NAME_DEF_INTERFACE => [ # overridden
            'doc' => '```phel
(definterface name & fns)
```
An interface in Phel defines an abstract set of functions. It is directly mapped to a PHP interface. An interface can be defined by using the definterface macro.',
            'url' => '/documentation/interfaces/#defining-interfaces',
            'fnSignature' => '(definterface name & fns)',
            'desc' => 'An interface in Phel defines an abstract set of functions. It is directly mapped to a PHP interface. An interface can be defined by using the definterface macro.',
        ],
    ];

    public function __construct(
        private RunFacadeInterface $runFacade,
    ) {
    }

    public function getNormalizedNativeSymbols(): array
    {
        return self::PRIVATE_SYMBOLS;
    }

    /**
     * @param  list<string>  $namespaces
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

    private function loadAllPhelFunctions(array $namespaces): void
    {
        Registry::getInstance()->clear();
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
        return Registry::getInstance()->getNamespaces();
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefinitionsInNamespace(string $ns): array
    {
        return Registry::getInstance()->getDefinitionInNamespace($ns);
    }

    private function getPhelMeta(string $ns, string $fnName): PersistentMapInterface
    {
        return Registry::getInstance()->getDefinitionMetaData($ns, $fnName)
            ?? TypeFactory::getInstance()->emptyPersistentMap();
    }
}
