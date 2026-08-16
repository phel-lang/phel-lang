# ADR 0016: A bare all-caps host name reads by position, not by probing

- **Status**: Accepted.
- **Date**: 2026-08-16
- **Extends**: [ADR 0015](0015-a-php-class-is-named-with-dots.md), which states that a
  class reference is identified lexically. This record removes the last place
  where it was not.

## Context

`PDO`, `WP_CLI` and `PHP_EOL` are spelled the same way: a bare name, all upper
case, no namespace. In PHP the first two are classes and the third is a global
constant, and nothing in the *name* says which.

Until [#3064](https://github.com/phel-lang/phel-lang/issues/3064) the resolver
asked the host. `SymbolResolver` probed `PhpClassLike::exists()`, which
autoloads, and read the name as a class when one turned up and as a constant
otherwise. The intent was to be independent of which form happened to load a
class first, and within one process it was. Across processes it was not:

```phel
(WP_CLI/log "x")
```

compiles to `\WP_CLI::log("x")` in a process where the class is autoloadable and,
in a process where it is not, to a dynamic call through a constant named
`WP_CLI` that does not exist either. Same source, two different compiled files,
decided by the bootstrap of whichever process ran the compiler. The
compiled-code cache then froze one of them, so the difference outlived the
process that caused it. Five positions were affected: value, static-call target,
`php/new`, `php/callable`, and property/constant access.

The probe was the last emission-affecting decision in the compiler that depended
on runtime state. ADR 0015 had already settled the principle for every other
spelling: *a class reference is identified lexically, never by reflection, so
meaning never depends on what happened to be loaded.*

## Decision

A bare all-caps host name reads by **where it stands**:

| Position | Reading | Example |
|---|---|---|
| Value | the global constant, always | `PHP_EOL`, `JSON_PRETTY_PRINT` |
| Member target | the class, always | `(WP_CLI/log "x")`, `(.-ATTR_ERRMODE PDO)` |
| Constructor | the class, always | `(php/new PDO $dsn)` |
| Callable | the class, always | `(php/callable PDO getAvailableDrivers)` |

Nothing is probed in any of them. A constant cannot have members and `new` needs
a class, so each class position states the intent in the source itself.

`php/NAME` keeps the constant reading everywhere, including as a `php/new` or
member target, so a constant holding a class string is still a usable dynamic
target. The class is reachable in value position three ways: `\NAME`,
`(:use NAME)`, or `NAME/class`.

Where value position now reads the constant and a class of that name is
loadable, the compiler warns through the always-on `CompilerWarnings` channel
and names those spellings. That probe survives only in the diagnostic, where
being environment-dependent costs a missed warning rather than a different
compilation.

## Consequences

- The compiled output of a source file no longer depends on the compiling
  process's autoloader, so the compiled-code cache cannot freeze the wrong
  reading.
- Every class position works for a class that is *not* loadable at compile time
  — the WP-CLI case the issue was raised for. Previously those compiled to
  constant-based code that failed at runtime.
- A bare all-caps name in value position that used to mean a class now means the
  constant. That is a breaking change, and a loud one: an undefined constant is
  a PHP `Error`, and the compiler warns at the site beforehand. It is silent
  only when both a class and a constant of that name exist, which is exactly the
  ambiguity being removed.
- A constant that holds a class string can no longer be a bare member target;
  `php/NAME` or a `let` binding says so explicitly.
- `looksLikeBareClassName`'s probe stays for lowercase-initial names
  (`stdClass`). There the alternative is not a second reading but an
  unresolved-symbol error, so it decides between "works" and "fails loudly".
- Two probes remain elsewhere and are deliberate: `:use` alias targets, asserted
  at compile time and loud when wrong, and the member-kind reflection in
  `QualifiedMemberExpander` for `\C/m` in value position, which Clojure has too.

## What breaks if this is broken

Reintroduce a probe in any of the five positions and the same source compiles to
different PHP in different processes again, with the cache preserving whichever
came first. Extend the class reading to value position instead, as
[#3064](https://github.com/phel-lang/phel-lang/issues/3064) originally proposed,
and bare `PHP_EOL` silently becomes the string `"PHP_EOL"` — a wrong value
rather than an error, which is the worse failure of the two.
