+++
title = "Namespaces"
weight = 12
+++

## Namespace (ns)

Every Phel file is required to have a namespace. A valid namespace name starts with a letter, followed by any number of letters, numbers, or dashes. Individual parts of the namespace are seperated by the `\` character. The last part of the namespace has to match the name of the file.

```phel
(ns name imports*)
```

Defines the namespace for the current file and adds imports to the environment. Imports can either be _uses_ or _requires_. The keyword `:use` is used to import PHP classes and the keyword `require` is used to import Phel modules.

```phel
(ns my\custom\module
  (:use \Some\Php\Class)
  (:require my\phel\module))
```

The call also sets the `*ns*` variable to the given namespace.

### Import a Phel module

To use a Phel module, you first have to import it with the keyword `:require`. Once imported you can access the module by its name followed by a slash and the name of the public function or value.

eg.

Let's say that we have a module called `util` defined in the `hello-world` namespace:

```phel
(ns hello-world\util)

(def my-name "Phel")

(defn greet [name]
    (print (str "Hello, " name)))
```

In the module where we want to use our `util` module we would call it like this:

```phel
(ns hello-world\boot
  (:require hello-world\util))

(util/greet util/my-name)
```

If you want you can set an alias for a module like so: `(:require hello-world\util :as utilities)`.

> To import a module from a different root namespace, you first have to add its path with `$rt->addPath('hello-mars\\', [__DIR__ . '/../mars']);` to `src/index.php`. **This is going to be changed in the future.**

### Import a PHP class

Importing a PHP class is optional. You can use any PHP class just by typing its namespace with the class name eg. `(php/new \Some\Php\ClassName)`. Once imported you can reference it by the class name eg. `(php/new ClassName)`.

If you want you can set an alias for a class like so: `(:use \Some\Php\ClassName :as BetterClassName)`.