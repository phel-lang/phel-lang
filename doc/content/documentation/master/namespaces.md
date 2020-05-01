+++
title = "Namespaces"
weight = 100
+++

## Namespace (ns)

```phel
(ns name imports*)
```

Defines the namespace for the current file add required imports to the environment. Imports can either be _uses_ or _requires_. _Uses_ are to import PHP classes and _require_ is used to import Phel modules.

```phel
(ns my\namespace\module
  (:use Some\Php\Class)
  (:require my\phel\module))
```