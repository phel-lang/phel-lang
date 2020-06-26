+++
title = "Namespaces"
weight = 12
+++

## Namespace (ns)

Every Phel file is required to have a namespace. A valid namespace name starts with a letter, followed by any number of letters, numbers, or dashes. Individual parts of the namespace are separated by the `\` character. The last part of the namespace has to match the name of the file.

```phel
(ns name imports*)
```

Defines the namespace for the current file and adds imports to the environment. Imports can either be _uses_ or _requires_. The keyword `:use` is used to import PHP classes and the keyword `require` is used to import Phel modules.

```phel
(ns my\custom\module
  (:use Some\Php\Class)
  (:require my\phel\module))
```

The call also sets the `*ns*` variable to the given namespace.

### Import a Phel module

Before a Phel module can be used, it has to be imported with the keyword `:require`. Once imported, the module can be accessed by its name followed by a slash and the name of the public function or value.

Given, a module `util` is defined in the namespace `hello-world`.

```phel
(ns hello-world\util)

(def my-name "Phel")

(defn greet [name]
  (print (str "Hello, " name)))
```

Module `boot` imports module `util` and uses its functions and values.

```phel
(ns hello-world\boot
  (:require hello-world\util))

(util/greet util/my-name)
```

To prevent name collision from other modules in different namespaces, aliases can be used.

```phel
(ns hello-world\boot
  (:require hello-world\util :as utilities))
```

Additionally, it is possible to refer symbols of other modules in the current namespace by using `:refer` keyword.

```phel
(ns hello-world\boot
  (:require hello-world\util :refer [greet]))

(greet util/my-name)
```

Both, `:refer` and `:as` can be combined in any order.

### Import a PHP class

PHP classes are imported with the keyword `:use`.

```phel
(ns my\custom\module
  (:use Some\Php\ClassName)
```

Once imported, a class can be referenced by its name.

```phel
(php/new ClassName)
```

To prevent name collision from other classes in different namespaces, aliases can be used.

```phel
(ns my\custom\module
  (:use Some\Php\ClassName :as BetterClassName)
```

Importing PHP classes is considered a "better" coding style, but it is optional. Any PHP class can be used by typing its namespace with the class name.

```phel
(php/new \Some\Php\ClassName)
```
