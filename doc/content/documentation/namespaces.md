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

eg. A module named `util` is defined in the `hello-world` namespace.

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

An alias for a module can be also be set:

```phel
(ns hello-world\boot
  (:require hello-world\util :as utilities))
```

> Importing a module from a different root namespace requires adding its path to `src/index.php`:
>
> ```
> $rt->addPath('hello-mars\\', [__DIR__ . '/../mars']);
> ```
>
> **This is going to be changed in the future.**

### Import a PHP class

Importing a PHP class is optional.

Any PHP class can be used just by typing its namespace with the class name:

```phel
(php/new Some\Php\ClassName)
```

PHP classes are imported with the keyword `:use`:

```phel
(ns my\custom\module
  (:use Some\Php\ClassName)
```

Once imported, a class can be referenced by its name:

```phel
(php/new ClassName)
```

An alias for a class can be also be set:

```phel
(ns my\custom\module
  (:use Some\Php\ClassName :as BetterClassName)
```
