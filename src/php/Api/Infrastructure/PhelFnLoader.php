<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure;

use Phar;
use Phel;
use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Symbol;
use Phel\Shared\Facade\RunFacadeInterface;
use RuntimeException;

use function dirname;
use function getcwd;
use function in_array;
use function is_dir;
use function mkdir;
use function rmdir;
use function sprintf;
use function uniqid;
use function unlink;

final readonly class PhelFnLoader implements PhelFnLoaderInterface
{
    private const array PRIVATE_SYMBOLS = [
        '*file*' => [
            'doc' => '```phel
*file*
```
Returns the path of the current source file.',
            'signatures' => ['*file*'],
            'desc' => 'Returns the path of the current source file.',
            'example' => '(println *file*) ; => "/path/to/current/file.phel"',
        ],
        '*ns*' => [
            'doc' => '```phel
*ns*
```
Returns the namespace in the current scope.',
            'signatures' => ['*ns*'],
            'desc' => 'Returns the namespace in the current scope.',
            'example' => '(println *ns*) ; => "my-app\\core"',
        ],
        Symbol::NAME_APPLY => [
            'doc' => '```phel
(apply f expr*)
```
Calls the function with the given arguments. The last argument must be a list of values, which are passed as separate arguments, rather than a single list. Apply returns the result of the calling function.',
            'docUrl' => '/documentation/functions-and-recursion/#apply-functions',
            'signatures' => ['(apply f expr*)'],
            'desc' => 'Calls the function with the given arguments. The last argument must be a list of values, which are passed as separate arguments, rather than a single list. Apply returns the result of the calling function.',
            'example' => '(apply + [1 2 3]) ; => 6',
        ],
        Symbol::NAME_CATCH => [
            'doc' => '```phel
(catch exception-type exception-name expr*)
```
Handle exceptions thrown in a `try` block by matching on the provided exception type. The caught exception is bound to `exception-name` while evaluating the expressions.',
            'docUrl' => '/documentation/control-flow/#try-catch-and-finally',
            'signatures' => ['(catch exception-type exception-name expr*)'],
            'desc' => 'Handle exceptions thrown in a `try` block by matching on the provided exception type. The caught exception is bound to exception-name while evaluating the expressions.',
            'example' => '(try (throw (php/new \Exception "error")) (catch \Exception e (php/-> e (getMessage))))',
        ],
        Symbol::NAME_CONJ => [
            'doc' => '```phel
(conj)
(conj coll)
(conj coll value)
(conj coll value & more)
```
Returns a new collection with values added. Appends to vectors/sets, prepends to lists.',
            'signatures' => ['(conj)', '(conj coll)', '(conj coll value)', '(conj coll value & more)'],
            'desc' => 'Returns a new collection with values added. Appends to vectors/sets, prepends to lists.',
            'docUrl' => '/documentation/data-structures/#adding-elements-with-conj',
            'example' => '(conj [1 2] 3) ; => [1 2 3]',
        ],
        Symbol::NAME_DEF => [
            'doc' => '```phel
(def name meta? value)
```
This special form binds a value to a global symbol.',
            'docUrl' => '/documentation/global-and-local-bindings/#definition-def',
            'signatures' => ['(def name meta? value)'],
            'desc' => 'This special form binds a value to a global symbol.',
            'example' => '(def my-value 42)',
        ],
        Symbol::NAME_DO => [
            'doc' => '```phel
(do expr*)
```
Evaluates the expressions in order and returns the value of the last expression. If no expression is given, nil is returned.',
            'docUrl' => '/documentation/control-flow/#statements-do',
            'signatures' => ['(do expr*)'],
            'desc' => 'Evaluates the expressions in order and returns the value of the last expression. If no expression is given, nil is returned.',
            'example' => '(do (println "Hello") (+ 1 2)) ; prints "Hello", returns 3',
        ],
        Symbol::NAME_DEF_EXCEPTION => [
            'doc' => '```phel
(defexception my-ex)
```
Define a new exception.',
            'docUrl' => '/documentation/exceptions',
            'signatures' => ['(defexception name)'],
            'desc' => 'Defines a new exception.',
            'example' => '(defexception my-error)',
        ],
        Symbol::NAME_DEF_INTERFACE => [
            'doc' => '```phel
(definterface name & fns)
```
An interface in Phel defines an abstract set of functions. It is directly mapped to a PHP interface. An interface can be defined by using the definterface macro.',
            'docUrl' => '/documentation/interfaces/#defining-interfaces',
            'signatures' => ['(definterface name & fns)'],
            'desc' => 'An interface in Phel defines an abstract set of functions. It is directly mapped to a PHP interface. An interface can be defined by using the definterface macro.',
            'example' => '(definterface Greeter (greet [name]))',
        ],
        Symbol::NAME_DEF_STRUCT => [
            'doc' => '```phel
(defstruct my-struct [a b c])
```
A Struct is a special kind of Map. It only supports a predefined number of keys and is associated to a global name. The Struct not only defines itself but also a predicate function.',
            'docUrl' => '/documentation/data-structures/#structs',
            'signatures' => ['(defstruct name [keys*])'],
            'desc' => 'A Struct is a special kind of Map. It only supports a predefined number of keys and is associated to a global name. The Struct not only defines itself but also a predicate function.',
            'example' => '(defstruct point [x y])',
        ],
        Symbol::NAME_FINALLY => [
            'doc' => '```phel
(finally expr*)
```
Evaluate expressions after the try body and all matching catches have completed. The finally block runs regardless of whether an exception was thrown.',
            'docUrl' => '/documentation/control-flow/#try-catch-and-finally',
            'signatures' => ['(finally expr*)'],
            'desc' => 'Evaluate expressions after the try body and all matching catches have completed. The finally block runs regardless of whether an exception was thrown.',
            'example' => '(defn risky-operation [] (throw (php/new \Exception "Error!")))' . PHP_EOL .
                '(defn cleanup [] (println "Cleanup!"))' . PHP_EOL .
                '(try (risky-operation) (catch \Exception e nil) (finally (cleanup)))',
        ],
        Symbol::NAME_FN => [
            'doc' => '```phel
(fn [params*] expr*)
```
Defines a function. A function consists of a list of parameters and a list of expression. The value of the last expression is returned as the result of the function. All other expression are only evaluated for side effects. If no expression is given, the function returns nil.',
            'docUrl' => '/documentation/functions-and-recursion/#anonymous-function-fn',
            'signatures' => ['(fn [params*] expr*)'],
            'desc' => 'Defines a function. A function consists of a list of parameters and a list of expression. The value of the last expression is returned as the result of the function. All other expression are only evaluated for side effects. If no expression is given, the function returns nil.',
            'example' => '(fn [x y] (+ x y))',
        ],
        Symbol::NAME_FOREACH => [
            'doc' => '```phel
(foreach [value valueExpr] expr*)
(foreach [key value valueExpr] expr*)
```
The foreach special form can be used to iterate over all kind of PHP datastructures. The return value of foreach is always nil. The loop special form should be preferred of the foreach special form whenever possible.',
            'signatures' => ['(foreach [value valueExpr] expr*)', '(foreach [key value valueExpr] expr*)'],
            'desc' => 'The foreach special form can be used to iterate over all kind of PHP datastructures. The return value of foreach is always nil. The loop special form should be preferred of the foreach special form whenever possible.',
            'docUrl' => '/documentation/control-flow/#foreach',
            'example' => '(foreach [x [1 2 3]] (println x))',
        ],
        Symbol::NAME_IF => [
            'doc' => '```phel
(if test then else?)
```
A control flow structure. First evaluates test. If test evaluates to true, only the then form is evaluated and the result is returned. If test evaluates to false only the else form is evaluated and the result is returned. If no else form is given, nil will be returned.

The test evaluates to false if its value is false or equal to nil. Every other value evaluates to true. In sense of PHP this means (test != null && test !== false).',
            'docUrl' => '/documentation/control-flow/#if',
            'signatures' => ['(if test then else?)'],
            'desc' => 'A control flow structure. First evaluates test. If test evaluates to true, only the then form is evaluated and the result is returned. If test evaluates to false only the else form is evaluated and the result is returned. If no else form is given, nil will be returned.',
            'example' => '(if (> x 0) "positive" "non-positive")',
        ],
        Symbol::NAME_LET => [
            'doc' => '```phel
(let [bindings*] expr*)
```
Creates a new lexical context with assignments defined in bindings. Afterwards the list of expressions is evaluated and the value of the last expression is returned. If no expression is given nil is returned.',
            'docUrl' => '/documentation/global-and-local-bindings/#local-bindings-let',
            'signatures' => ['(let [bindings*] expr*)'],
            'desc' => 'Creates a new lexical context with assignments defined in bindings. Afterwards the list of expressions is evaluated and the value of the last expression is returned. If no expression is given nil is returned.',
            'example' => '(let [x 1 y 2] (+ x y)) ; => 3',
        ],
        Symbol::NAME_LIST => [
            'doc' => '```phel
(list 1 2 3) ; => \'(1 2 3)
```
Creates a new list. If no argument is provided, an empty list is created. Shortcut: \'()',
            'docUrl' => '/documentation/data-structures/#lists',
            'signatures' => ['(list & xs)'],
            'desc' => 'Creates a new list. If no argument is provided, an empty list is created.',
            'example' => "(list 1 2 3) ; => '(1 2 3)",
        ],
        Symbol::NAME_LOOP => [
            'doc' => '```phel
(loop [bindings*] expr*)
```
Creates a new lexical context with variables defined in bindings and defines a recursion point at the top of the loop.',
            'signatures' => ['(loop [bindings*] expr*)'],
            'desc' => 'Creates a new lexical context with variables defined in bindings and defines a recursion point at the top of the loop.',
            'docUrl' => '/documentation/control-flow/#loop',
            'example' => '(loop [i 0] (if (< i 5) (do (println i) (recur (inc i)))))',
        ],
        Symbol::NAME_MAP => [
            'doc' => '```phel
(hash-map :a 1 :b 2) ; => {:a 1 :b 2}
```
Creates a new hash map. If no argument is provided, an empty hash map is created. The number of parameters must be even. Shortcut: {}',
            'docUrl' => '/documentation/data-structures/#maps',
            'signatures' => ['(hash-map & xs)'],
            'desc' => 'Creates a new hash map. If no argument is provided, an empty hash map is created. The number of parameters must be even.',
            'example' => '(hash-map :name "Alice" :age 30) ; => {:name "Alice" :age 30}',
        ],
        Symbol::NAME_NS => [
            'doc' => '```phel
(ns name imports*)
```
Defines the namespace for the current file and adds imports to the environment. Imports can either be uses or requires. The keyword `:use` is used to import PHP classes, the keyword `:require` is used to import Phel modules and the keyword `:require-file` is used to load php files.',
            'docUrl' => '/documentation/namespaces/#namespace-ns',
            'signatures' => ['(ns name imports*)'],
            'desc' => 'Defines the namespace for the current file and adds imports to the environment. Imports can either be uses or requires. The keyword :use is used to import PHP classes, the keyword :require is used to import Phel modules and the keyword :require-file is used to load php files.',
            'example' => '(ns my-app\\core (:require phel\\str :as str))',
        ],
        Symbol::NAME_PHP_ARRAY_GET => [
            'doc' => '```phel
(php/aget arr index)
```
Equivalent to PHP\'s `arr[index] ?? null`.',
            'docUrl' => '/documentation/php-interop/#get-php-array-value',
            'signatures' => ['(php/aget arr index)'],
            'desc' => "Equivalent to PHP's `arr[index] ?? null`.",
            'example' => '(php/aget (php/array "a" "b" "c") 1) ; => "b"',
        ],
        Symbol::NAME_PHP_ARRAY_GET_IN => [
            'doc' => '```phel
(php/aget-in arr ks)
```
Equivalent to PHP\'s `arr[k1][k2][k...] ?? null`.',
            'docUrl' => '/documentation/php-interop/#get-php-array-value',
            'signatures' => ['(php/aget-in arr ks)'],
            'desc' => "Equivalent to PHP's `arr[k1][k2][k...] ?? null`.",
            'example' => '(php/aget-in nested-arr ["users" 0 "name"])',
        ],
        Symbol::NAME_PHP_ARRAY_SET => [
            'doc' => '```phel
(php/aset arr index value)
```
Equivalent to PHP\'s `arr[index] = value`.',
            'docUrl' => '/documentation/php-interop/#set-php-array-value',
            'signatures' => ['(php/aset arr index value)'],
            'desc' => "Equivalent to PHP's `arr[index] = value`.",
            'example' => '(php/aset arr 0 "new-value")',
        ],
        Symbol::NAME_PHP_ARRAY_SET_IN => [
            'doc' => '```phel
(php/aset-in arr ks value)
```
Equivalent to PHP\'s `arr[k1][k2][k...] = value`.',
            'docUrl' => '/documentation/php-interop/#set-php-array-value',
            'signatures' => ['(php/aset-in arr ks value)'],
            'desc' => "Equivalent to PHP's `arr[k1][k2][k...] = value`.",
            'example' => '(php/aset-in arr ["users" 0 "name"] "Alice")',
        ],
        Symbol::NAME_PHP_ARRAY_PUSH => [
            'doc' => '```phel
(php/apush arr value)
```
Equivalent to PHP\'s `arr[] = value`.',
            'docUrl' => '/documentation/php-interop/#append-php-array-value',
            'signatures' => ['(php/apush arr value)'],
            'desc' => "Equivalent to PHP's arr[] = value.",
            'example' => '(php/apush arr "new-item")',
        ],
        Symbol::NAME_PHP_ARRAY_PUSH_IN => [
            'doc' => '```phel
(php/apush-in arr ks value)
```
Equivalent to PHP\'s `arr[k1][k2][k...][] = value`.',
            'docUrl' => '/documentation/php-interop/#append-php-array-value',
            'signatures' => ['(php/apush-in arr ks value)'],
            'desc' => "Equivalent to PHP's `arr[k1][k2][k...][] = value`.",
            'example' => '(php/apush-in arr ["users"] {:name "Bob"})',
        ],
        Symbol::NAME_PHP_ARRAY_UNSET => [
            'doc' => '```phel
(php/aunset arr index)
```
Equivalent to PHP\'s `unset(arr[index])`.',
            'docUrl' => '/documentation/php-interop/#unset-php-array-value',
            'signatures' => ['(php/aunset arr index)'],
            'desc' => "Equivalent to PHP's `unset(arr[index])`.",
            'example' => '(php/aunset arr "key-to-remove")',
        ],
        Symbol::NAME_PHP_ARRAY_UNSET_IN => [
            'doc' => '```phel
(php/aunset-in arr ks)
```
Equivalent to PHP\'s `unset(arr[k1][k2][k...])`.',
            'docUrl' => '/documentation/php-interop/#unset-php-array-value',
            'signatures' => ['(php/aunset-in arr ks)'],
            'desc' => "Equivalent to PHP's `unset(arr[k1][k2][k...])`.",
            'example' => '(php/aunset-in arr ["users" 0])',
        ],
        Symbol::NAME_PHP_NEW => [
            'doc' => '```phel
(php/new expr args*)
```
Evaluates expr and creates a new PHP class using the arguments. The instance of the class is returned.',
            'docUrl' => '/documentation/php-interop/#php-class-instantiation',
            'signatures' => ['(php/new expr args*)'],
            'desc' => 'Evaluates expr and creates a new PHP class using the arguments. The instance of the class is returned.',
            'example' => '(php/new DateTime "2024-01-01")',
        ],
        Symbol::NAME_PHP_OBJECT_CALL => [
            'doc' => '```phel
(php/-> object call*)
(php/:: class call*)
```
Access to an object property or result of chained calls.',
            'docUrl' => '/documentation/php-interop/#php-set-object-properties',
            'signatures' => ['(php/-> object call*)', '(php/:: class call*)'],
            'desc' => 'Access to an object property or result of chained calls.',
            'example' => '(php/-> date (format "Y-m-d"))',
        ],
        Symbol::NAME_PHP_OBJECT_SET => [
            'doc' => '```phel
(php/oset (php/-> object property) value)
(php/oset (php/:: class property) value)
```
Use `php/oset` to set a value to a class/object property.',
            'docUrl' => '/documentation/php-interop/#php-set-object-properties',
            'signatures' => ['(php/oset (php/-> object property) value)', '(php/oset (php/:: class property) value)'],
            'desc' => 'Use `php/oset` to set a value to a class/object property.',
            'example' => '(php/oset (php/-> obj name) "Alice")',
        ],
        Symbol::NAME_PHP_OBJECT_STATIC_CALL => [
            'doc' => '```phel
(php/:: class (method-name expr*))
(php/:: class call*)
```
Calls a static method or property from a PHP class. Both method-name and property must be symbols and cannot be an evaluated value.',
            'docUrl' => '/documentation/php-interop/#php-static-method-and-property-call',
            'signatures' => ['(php/:: class (method-name expr*))', '(php/:: class call*)'],
            'desc' => 'Calls a static method or property from a PHP class. Both method-name and property must be symbols and cannot be an evaluated value.',
            'example' => '(php/:: DateTime (createFromFormat "Y-m-d" "2024-01-01"))',
        ],
        Symbol::NAME_QUOTE => [
            'doc' => '```phel
(quote form)
```
Returns the unevaluated form.',
            'signatures' => ['(quote form)'],
            'desc' => 'Returns the unevaluated form.',
            'docUrl' => '/documentation/macros/#quote',
            'example' => "(quote (+ 1 2)) ; => '(+ 1 2)",
        ],
        Symbol::NAME_RECUR => [
            'doc' => '```phel
(recur expr*)
```
Internally recur is implemented as a PHP while loop and therefore prevents the Maximum function nesting level errors.',
            'docUrl' => '/documentation/functions-and-recursion/#recursion',
            'signatures' => ['(recur expr*)'],
            'desc' => 'Internally recur is implemented as a PHP while loop and therefore prevents the Maximum function nesting level errors.',
            'example' => '(loop [n 5 acc 1] (if (<= n 1) acc (recur (dec n) (* acc n))))',
        ],
        Symbol::NAME_SET_VAR => [
            'doc' => '```phel
(var value)
```
Variables provide a way to manage mutable state that can be updated with `set!` and `swap!`. Each variable contains a single value. To create a variable use the var function.',
            'docUrl' => '/documentation/global-and-local-bindings/#variables',
            'signatures' => ['(var value)'],
            'desc' => 'Variables provide a way to manage mutable state that can be updated with `set!` and `swap!`. Each variable contains a single value. To create a variable use the var function.',
            'example' => '(def counter (var 0))',
        ],
        Symbol::NAME_THROW => [
            'doc' => '```phel
(throw exception)
```
Throw an exception.',
            'docUrl' => '/documentation/control-flow/#try-catch-and-finally',
            'signatures' => ['(throw exception)'],
            'desc' => 'Throw an exception.',
            'example' => '(throw (php/new \InvalidArgumentException "Invalid input"))',
        ],
        Symbol::NAME_TRY => [
            'doc' => '```phel
(try expr* catch-clause* finally-clause?)
```
All expressions are evaluated and if no exception is thrown the value of the last expression is returned. If an exception occurs and a matching catch-clause is provided, its expression is evaluated and the value is returned. If no matching catch-clause can be found the exception is propagated out of the function. Before returning normally or abnormally the optionally finally-clause is evaluated.',
            'docUrl' => '/documentation/control-flow/#try-catch-and-finally',
            'signatures' => ['(try expr* catch-clause* finally-clause?)'],
            'desc' => 'All expressions are evaluated and if no exception is thrown the value of the last expression is returned. If an exception occurs and a matching catch-clause is provided, its expression is evaluated and the value is returned. If no matching catch-clause can be found the exception is propagated out of the function. Before returning normally or abnormally the optionally finally-clause is evaluated.',
            'example' => '(try (/ 1 0) (catch \Exception e "error"))',
        ],
        Symbol::NAME_UNQUOTE => [
            'doc' => '```phel
(unquote my-sym) ; Evaluates to my-sym
,my-sym          ; Shorthand for (same as above)
```
Values that should be evaluated in a macro are marked with the unquote function. Shortcut: `,`',
            'docUrl' => '/documentation/macros/#quasiquote',
            'signatures' => ['(unquote expr)'],
            'desc' => 'Values that should be evaluated in a macro are marked with the unquote function. Shortcut: ,',
            'example' => '`(+ 1 ,(+ 2 3)) ; => (+ 1 5)',
        ],
        Symbol::NAME_UNQUOTE_SPLICING => [
            'doc' => '```phel
(unquote-splicing my-sym) ; Evaluates to my-sym
,@my-sym                  ; Shorthand for (same as above)
```
Values that should be evaluated in a macro are marked with the unquote function. Shortcut: `,@`',
            'docUrl' => '/documentation/macros/#quasiquote',
            'signatures' => ['(unquote-splicing expr)'],
            'desc' => 'Values that should be evaluated in a macro are marked with the unquote function. Shortcut: ,@',
            'example' => '`(+ ,@[1 2 3]) ; => (+ 1 2 3)',
        ],
        Symbol::NAME_VECTOR => [
            'doc' => '```phel
(vector 1 2 3) ; => [1 2 3]
```
Creates a new vector. If no argument is provided, an empty vector is created. Shortcut: []',
            'docUrl' => '/documentation/data-structures/#vectors',
            'signatures' => ['(vector & xs)'],
            'desc' => 'Creates a new vector. If no argument is provided, an empty vector is created.',
            'example' => '(vector 1 2 3) ; => [1 2 3]',
        ],
    ];

    public function __construct(
        private RunFacadeInterface $runFacade,
    ) {
    }

    /**
     * @return array<string,array{
     *     doc?: string,
     *     signatures?: list<string>,
     *     desc?: string,
     *     docUrl?: string,
     *     example?: string,
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

        // When running in a phar, we can't write to __DIR__ due to phar.readonly
        // Use a temporary directory in the current working directory instead
        if (Phar::running() !== '') {
            $cwd = getcwd();
            if ($cwd === false) {
                throw new RuntimeException('Unable to determine current working directory.');
            }

            $tempDir = $cwd . '/.phel_temp_' . uniqid('', true);
            if (!mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
                throw new RuntimeException(sprintf('Unable to create temporary directory at "%s".', $tempDir));
            }

            $phelFile = $tempDir . '/doc.phel';
        } else {
            $phelDir = __DIR__ . '/phel';
            if (!is_dir($phelDir) && !mkdir($phelDir, 0755, true) && !is_dir($phelDir)) {
                throw new RuntimeException(sprintf('Unable to create directory at "%s".', $phelDir));
            }

            $phelFile = $phelDir . '/doc.phel';
        }

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

        if (isset($tempDir)) {
            @rmdir($tempDir);
        }

        // Clean up temporary directory if running in phar
        if (Phar::running() !== '' && dirname($phelFile) !== __DIR__) {
            rmdir(dirname($phelFile));
        }
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
