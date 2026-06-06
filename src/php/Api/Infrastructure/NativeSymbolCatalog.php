<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure;

use Phel\Lang\Symbol;

/**
 * Static documentation catalog for Phel symbols that have no `.phel` source
 * definition: special forms (`if`, `fn`, `def`, ...) and built-in runtime
 * symbols (`*file*`, `*ns*`). Their `:doc`/`:signatures`/`:example` metadata
 * cannot be read from the registry, so it is maintained here and merged by
 * {@see PhelFnLoader} with the runtime-loaded function metadata.
 *
 * Maintenance: adding a native symbol means appending an entry to the
 * {@see self::DEFINITIONS} array by hand (there is no generator); if it
 * introduces a new namespace, also register that namespace in
 * {@see \Phel\Api\ApiConfig::allNamespaces()} so it is surfaced by the loader.
 */
final class NativeSymbolCatalog
{
    /**
     * @var array<string,array{
     *     doc?: string,
     *     signatures?: list<string>,
     *     desc?: string,
     *     docUrl?: string,
     *     example?: string,
     *     file?: string,
     *     line?: int,
     * }>
     */
    private const array DEFINITIONS = [
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
            'example' => '(println *ns*) ; => "my-app.core"',
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
        Symbol::NAME_DEF_ONCE => [
            'doc' => '```phel
(defonce name meta? value)
```
Like `def`, but only binds the value when `name` is not already defined in the registry. Useful in REPL workflows where re-evaluating a file should not reset stateful holders (atoms, connections, caches).',
            'docUrl' => '/documentation/global-and-local-bindings/#definition-def',
            'signatures' => ['(defonce name meta? value)'],
            'desc' => 'Like `def`, but only binds the value when `name` is not already defined.',
            'example' => '(defonce app-state (atom {}))',
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
(defexception my-ex \RuntimeException)
```
Define a new exception, optionally extending a custom parent class (defaults to \Exception).',
            'docUrl' => '/documentation/exceptions',
            'signatures' => ['(defexception name)', '(defexception name parent)'],
            'desc' => 'Defines a new exception, optionally extending a custom parent class.',
            'example' => '(defexception my-error \RuntimeException)',
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
            'example' => '(defn risky-operation [] (throw (php/new \Exception "Error!")))' . PHP_EOL
                . '(defn cleanup [] (println "Cleanup!"))' . PHP_EOL
                . '(try (risky-operation) (catch \Exception e nil) (finally (cleanup)))',
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
        Symbol::NAME_IN_NS => [
            'doc' => '```phel
(in-ns namespace)
```
Switches to an existing namespace without creating it. Intended for REPL use, e.g. navigating into a namespace to inspect or test private functions interactively. Avoid in source files: the build system assumes one namespace per file, and `in-ns` causes collisions in the dependency resolver.',
            'docUrl' => '/documentation/namespaces/',
            'signatures' => ['(in-ns namespace)'],
            'desc' => 'Switches to an existing namespace without creating it (REPL-oriented).',
            'example' => '(in-ns my-app\\core)',
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
        Symbol::NAME_LOAD => [
            'doc' => '```phel
(load path)
```
Loads a Phel source file into the caller namespace at runtime. Path resolution follows the spirit of Clojure\'s `clojure.core/load`: a path beginning with a slash is classpath-absolute (searched against the configured `phel\repl/src-dirs` roots); otherwise it is resolved relative to the caller file\'s compile-time location, so mutations to the runtime `*file*` value cannot break resolution. Pass the path without an extension (no `.phel`) and without a relative prefix (no `./` or `../`). Returns nil; the form runs for its side effects.',
            'docUrl' => '/documentation/namespaces/',
            'signatures' => ['(load path)'],
            'desc' => 'Loads a Phel source file into the caller namespace at runtime, resolving the path relative to the caller file or against the configured classpath roots.',
            'example' => '(load "core/meta") ; loads and evaluates core/meta into the current namespace',
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
            'example' => '(ns my-app\\core (:require phel\\string :as str))',
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
        Symbol::NAME_PHP_CALLABLE => [
            'doc' => '```phel
(php/callable \function)
(php/callable Class method)
(php/callable object method)
```
Builds a native PHP 8.1 first-class callable `(...)` from a free function, a static method, or an instance method, without allocating an `fn` wrapper.',
            'docUrl' => '/documentation/php-interop/#php-first-class-callable',
            'signatures' => ['(php/callable \function)', '(php/callable Class method)', '(php/callable object method)'],
            'desc' => 'Builds a native PHP first-class callable from a function or method, without an fn wrapper.',
            'example' => '(map (php/callable \strtoupper) ["a" "b"])',
        ],
        Symbol::NAME_PHP_REF => [
            'doc' => '```phel
(php/-> object (method (php/ref local)))
```
Marks a local variable as passed by reference in a `php/->`/`php/::` interop call, so an output-parameter PHP method can write back into the Phel binding.',
            'docUrl' => '/documentation/php-interop/',
            'signatures' => ['(php/ref local)'],
            'desc' => 'Passes a local variable by reference into a PHP interop call.',
            'example' => '(php/-> stmt (bindColumn 1 (php/ref out)))',
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
        Symbol::NAME_USE => [
            'doc' => '```phel
(use ClassName [:as Alias])
```
Registers PHP class aliases in the current namespace without the full `(ns ... (:use ...))` form. Intended for files that join an existing namespace via `(in-ns ...)` and only want to declare the imports they actually use. Pure compile-time registration; emits no runtime code.',
            'docUrl' => '/documentation/namespaces/',
            'signatures' => ['(use ClassName & options)'],
            'desc' => 'Registers PHP class aliases in the current namespace (compile-time only).',
            'example' => '(use \\DateTimeImmutable :as Date)',
        ],
        Symbol::NAME_VAR => [
            'doc' => '```phel
(var sym)
```
Resolves `sym` against the current namespace, require aliases, and refers, yielding the first-class `Var` handle for that global definition. The reader shorthand `#\'sym` expands to `(var sym)`. Throws if `sym` does not resolve to a known global.',
            'docUrl' => '/documentation/global-and-local-bindings/#variables',
            'signatures' => ['(var sym)'],
            'desc' => "Returns the Var handle for a global definition; reader shorthand is #'sym.",
            'example' => '(var map) ; resolves to the Var for phel.core/map',
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
    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }
}
