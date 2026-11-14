# ADR 0023: Build source paths follow the artifact

- **Status**: Accepted
- **Date**: 2026-11-14

## Context

`phel build` embedded the build machine's source paths in `*file*`, definition metadata and cached secondary source maps (#3882). This exposed host paths and made identical sources produce different artifacts in different directories. The build already ships a source copy beside each compiled namespace for error reporting.

The compiler also needs original paths while it runs macros and loads source files. Its FILE output can be evaluated from a temporary PHP file during compilation. Replacing source paths with PHP `__DIR__` expressions during emission would therefore resolve against the evaluator's temporary directory. Warm builds also load existing FILE artifacts, so their macros still need access to original adjacent files.

## Decision

Deployable source locations name the `.phel` copies shipped beside the generated PHP. Build records the complete source-to-output mapping and relocates source-path slots after compilation and secondary harvesting. Each stored path resolves relative to its compiled artifact at runtime.

The compiler and its persistent runtime cache keep original source paths. Magic constants retain their provenance as compiler nodes and tagged emission until the artifact pass, so an identical user-written string remains data. PHP's explicit `php/__FILE__` and `php/__DIR__` keep their native generated-PHP meaning.

During a build, a scoped source map resolves relocated artifact paths back to the original files. This preserves the behavior of macros loaded from warm artifacts. The previous scope is restored even when compilation fails.

## Consequences

Artifacts do not store compiler-owned absolute project or standard-library paths. Source metadata and errors work after moving the artifact without its original source tree. Ordinary strings, including paths deliberately written by users or returned as macro data, are preserved and can still contain absolute paths.

The artifact pass preserves incremental-build mtimes and updates generated source-map columns. Secondary files use the same source-copy and map layout as primary files. Compiler caches retain their existing path identity and invalidation rules.

Generated artifacts still require their generating runtime version under [ADR 0020](0020-build-artifacts-require-the-generating-version.md).

## Enforcement

`BuildCommandPortablePathsTest` compares complete output trees built in different-length directories (including a line feed on supported filesystems), moves a build, runs nested loads, checks magic constants and metadata, maps an uncaught error, preserves an identical user string, and recompiles a caller using a warm macro that reads an adjacent file. `BuildCommandLoadE2ETest` checks that generated PHP contains no project or standard-library source prefix. `ArtifactSourcePathsTest` and `ArtifactSourceMapTest` pin token and mapping behavior.

## Alternatives considered

- Relative strings everywhere: breaks compile-time loads and macro file access.
- Emit runtime `__DIR__` paths directly: FILE fragments can run from an evaluator temporary file.
- Replace every matching string: changes user data and loses the distinction between paths and ordinary strings.
- Keep build-host paths for diagnostics: retains the disclosure and reproducibility failures.

## See also

`.agnostic-ai/rules/module-build.md`, `.agnostic-ai/rules/module-compiler-emitter.md`, [ADR 0020](0020-build-artifacts-require-the-generating-version.md).
