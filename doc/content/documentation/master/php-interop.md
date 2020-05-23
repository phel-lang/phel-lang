+++
title = "PHP Interop"
weight = 100
+++

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
(php/-> (methodname expr*))
(php/-> property)
```

Calls a method or property on a PHP object. Both `methodname` and `property` must be symbols and can not be a evaluated value.

```phel
(ns my\module
  (:use \DateInterval))

(def di (php/new \DateInterval "PT30S"))

(php/-> di (format "%s seconds")) # Evaluates to "30 seconds"
(php/-> di s) # Evaluates to 30
```

## PHP static method and property call

```phel
(php/:: (methodname expr*))
(php/:: property)
```

Same as above, but for static calls on PHP classes.

```phel
(ns my\module
  (:use \DateTimeImmutable))

(php/:: DateTimeImmutable ATOM) # Evaluates to "Y-m-d\TH:i:sP"

# Evaluates to a new instance of DateTimeImmutable
(php/:: DateTimeImmutable (createFromFormat "Y-m-d" "2020-03-22")) 

```

## Get PHP-Array value

```phel
(php/aget arr index)
```

Equivalent to PHP's `arr[index] ?? null`.

```phel
(php/aget ["a" "b" "c"] 0) # Evaluates to "a"
(php/aget (php/array "a" "b" "c") 1) # Evaluates to "1"
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