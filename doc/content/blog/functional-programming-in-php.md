+++
title = "My attempt on functional programming in PHP"
template = "blog-entry.html"
+++

PHP was one of my first languages I learned. Even so, this dates back over 10 years, I still use PHP every day at work. However, in the meantime I also learned a lot of other languages like Java, Clojure, Scala, Python, Javascript and Scheme. By learning all the languages and their concepts, the concept of functional programming always was my favorite and so I tried to make my PHP programming style more functional. In the following article you can read some approaches I tried.

## Functions as arguments

Defining a function in PHP is quite easy

```php
<?php

function inc($number) {
    return $number + 1;
}
```

However, one of the most common things you do in functional programming is to pass a function as argument to another function. If you try that, PHP will response to you with an error message.

```php
<?php

$x = [1, 2, 3, 4];
array_map(inc, $x);
// PHP Warning:  Use of undefined constant inc
```

PHP has no direct support for this. To fix the problem we must convert the symbol `inc` into a string.

```php
<?php

$x = [1, 2, 3, 4];
array_map('inc', $x);
```

A more common way is to define a constant for each function and use the constant as parameter to the function.

```php
<?php

function inc($number) {
    return $number + 1;
}
const inc = 'inc';

$x = [1, 2, 3, 4];
array_map(inc, $x);
```

The disadvantage of such an approach is, that refactoring is more challenging and the chance of missing a constant definition is very high.

## Class modules

Another common concept in functional programming it the possibility to group a set of function to a module. A module can have a few public accessible functions and some private functions that are not accessible to the outside world. In PHP this can be accomplished by creating a class using only static members.

```php
<?php

class MyStaticModule {
    private static function add($a, $b) {
        return $a + $b;
    }

    public static function inc($number) {
        return self::add($number, 1);
    }
}
```

This approach gives us all the flexibility in terms of modularity. However, the problem of passing functions as arguments for another function hasn't been solved.

## Trait modules

A second approach on modularity is to use traits.

```php
<?php

trait MyTraitModule {

    public function inc($number) {
        return $number + 1;
    }
}
```

After defining all traits you have to create a class that uses these traits.

```php
<?php

class MyProgram {
    use MyTraitModule;
    // use Other Traits here

    public function main() {
        return $this->inc($number);
    }
}
```

A benefit of this approach is that we can solve our first problem. Therefore, we just need to define the magic method `__get`.

```php
<?php

trait FPMagic {
    public function __get($name) {
        if (method_exists($this, $name)) {
            return [$this, $name]; // This is callable
        } else {
            throw new \Exception('Method does not exists: ' . $name);
        }
    }
}

class MyProgram {
    use FPMagic;
    use MyTraitModule;

    public function main() {
        $x = [1, 2, 3, 4];
        return array_map($this->inc, $x); // Works using the magic method __get
    }
}
```

A big disadvantage of this approach is, that we have to resolve all conflicting function names ourself. Not only for public methods but also for private methods. This becomes next to impossible if the program grows.

A combination of the class module approach and the trait module approach would be a good solution to get started with functional programming in PHP. However, the trick with the magic method `_get` cannot be used for the class module approach, since PHP has no magic method for static properties.

## Alternatives

One last alternative is to use a language that is functional and compiles to PHP. In recent years, there have been a few attempts on this (e.g. [Haxe](https://haxe.org/) and [Pharen](http://www.pharen.org/)). While Pharen looked very promising, it hasn't seen any commits for a few years now and is still based on PHP 5.

## Introducing Phel

Since I didn't found a good solution for this problem, I used my coronavirus spare time to solve it by myself. This is what turns out to be Phel. Phel is a functional programming language that compiles to PHP. It is a dialect of Lisp inspired by [Clojure](https://clojure.org/) and [Janet](https://janet-lang.org/). While the status of Phel is currently alpha, it is fairly complete. If you want to try it, go ahead and read the [Getting started guide](/documentation/getting-started/). I'm looking for your feedback.
