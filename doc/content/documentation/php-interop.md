+++
title = "PHP Interop"
weight = 13
+++

## Calling PHP functions

PHP comes with huge set of functions that can be called from Phel by just adding a `php/` prefix to the function name.

```
(php/strlen "test") # Calls PHP's strlen function and evaluates to 4
(php/date "l") # Evaluates to something like "Monday"
```

## PHP class instantiation

```phel
(php/new expr args*)
```

Evaluates `expr` and creates a new PHP class using the arguments. The instance of the class is returned.

```phel
(ns my\module
  (:use \DateTime))

(php/new DateTime) # Returns a new instance of the DateTime class
(php/new DateTime "now") # Returns a new instance of the DateTime class

(php/new "\\DateTimeImmutable") # instantiate a new PHP class from string
```

## PHP method and property call

```phel
(php/-> object (methodname expr*))
(php/-> object property)
```

Calls a method or property on a PHP object. Both `methodname` and `property` must be symbols and cannot be an evaluated value.

```phel
(ns my\module
  (:use \DateInterval))

(def di (php/new \DateInterval "PT30S"))

(php/-> di (format "%s seconds")) # Evaluates to "30 seconds"
(php/-> di s) # Evaluates to 30
```

## PHP static method and property call

```phel
(php/:: class (methodname expr*))
(php/:: class property)
```

Same as above, but for static calls on PHP classes.

```phel
(ns my\module
  (:use \DateTimeImmutable))

(php/:: DateTimeImmutable ATOM) # Evaluates to "Y-m-d\TH:i:sP"

# Evaluates to a new instance of DateTimeImmutable
(php/:: DateTimeImmutable (createFromFormat "Y-m-d" "2020-03-22"))

```

## PHP set object properties

```phel
(php/oset (php/-> object property) value)
(php/oset (php/:: class property) value)
```

Use `php/oset` to set a value to a class/object property.

```phel
(def x (php/new \stdclass))
(php/oset (php/-> x name) "foo")
```

## Get PHP-Array value

```phel
(php/aget arr index)
```

Equivalent to PHP's `arr[index] ?? null`.

```phel
(php/aget ["a" "b" "c"] 0) # Evaluates to "a"
(php/aget (php/array "a" "b" "c") 1) # Evaluates to "b"
(php/aget (php/array "a" "b" "c") 5) # Evaluates to nil
```

## Set PHP-Array value

```phel
(php/aset arr index value)
```

Equivalent to PHP's `arr[index] = value`.

## Append PHP-Array value

```phel
(php/apush arr value)
```

Equivalent to PHP's `arr[] = value`.

## Unset PHP-Array value

```phel
(php/aunset arr index)
```

Equivalent to PHP's `unset(arr[index])`.

## `__DIR__` and `__FILE__`

In Phel you can also use PHP Magic Methods `__DIR__` and `__FILE__`. These resolve to the dirname or filename of the Phel file.

```phel
(println __DIR__) # Prints the directory name of the file
(println __FILE__) # Prints the filename of the file
```

## Calling Phel functions from PHP

Phel also provides a way to let you call Phel function from PHP. This is useful for existing PHP application that wants to integrate Phel. Therefore, you have to load the Phel Runtime at the beginning of your script. This can be done directly after the `autoload.php` file was loaded.

```php
require_once 'vendors/autoload.php';
require_once 'vendors/PhelRuntime.php';
```

Phel provide two ways to call Phel functions, manually or by using the `export` command.

### Manually

The `PhelCallerTrait` can be used to call any Phel function from an existing PHP class.
Simply inject the trait in the class and call the `callPhel` function.

```php
use Phel\Interop\PhelCallerTrait;

class MyExistingClass {
  use PhelCallerTrait;

  public function myExistingMethod(...$arguments) {
    return $this->callPhel('my\phel\namespace', 'phel-function-name', ...$arguments);
  }
}
```

### Using the `export` command

Alternatively, the `phel export` command can be used. This command will generate a wrapper class for all Phel functions that are marked as *export*.

Before using the `export` command the required configuration options need to be added to `composer.json`:

```json
{
  "export": {
    "directories": [
        "src/phel"
    ],
    "namespace-prefix": "PhelGenerated",
    "target-directory": "src/PhelGenerated"
  }
}
```

A detailed description of the options can be found in the [Configuration](/documentation/configuration/#export) chapter.

To mark a function as exported the following meta data needs to be added to the function:

```phel
(defn my-function
  {:export true}
  [a b]
  (+ a b))
```

Now the `phel export` command will generate a PHP wrapper class in the target directory (in this case `src/PhelGenerated`). This class can then be used in the PHP application to call Phel functions.
