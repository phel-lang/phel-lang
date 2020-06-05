+++
title = "Namespaces"
weight = 12
+++

## Namespace (ns)

Every Phel file is required to have a namespace. Individual parts of the namespace are seperated by the `\` character. The last part of the namespace has to match the name of the file.

```phel
(ns name imports*)
```

Defines the namespace for the current file add required imports to the environment. Imports can either be _uses_ or _requires_. The keyword `:use` is used to import PHP classes and the keyword `require` is used to import Phel modules.

```phel
(ns my\custom\module
  (:use Some\Php\Class)
  (:require my\phel\module))
```

The call also set the `*ns*` variable to the given namespace.