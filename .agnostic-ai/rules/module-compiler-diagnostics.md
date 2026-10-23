---
name: module-compiler-diagnostics
description: 'Compiler warnings and deprecation notices: channels, detectors, gating and attribution.'
scope: src/php/Compiler/Domain/Diagnostic
globs:
  - src/php/Compiler/Domain/Deprecation/**
---

# Compiler Diagnostics

Part of the Compiler module; read `.agnostic-ai/rules/module-compiler.md` for the pipeline and constraints.

## Channels

Two channels, differing only in the gate. Both raise through `Domain/Diagnostic/ErrorNotice::raise($message, $level)`, which writes one stderr line prefixed `deprecated: ` or `warning: ` (ADR 0017).

| Channel | Level | Gate |
|---|---|---|
| `Domain/Deprecation/DeprecationWarnings` | `E_USER_DEPRECATED` | off unless `--warn-deprecations` |
| `Domain/Diagnostic/CompilerWarnings` | `E_USER_WARNING` | always on |

`CompilerWarnings` is for diagnostics that already changed program behaviour, so silence is the bug. It owns bundled-stdlib suppression (via `DeprecationWarnings::isBundledStdlibSource()`) and per-`(file, subject)` dedup; `reset()` is for test `tearDown()`. Both detectors are called from `DefSymbol` before `addDefinition`, so they warn once at `def` time like Clojure, not per call site:

| Detector | Catches |
|---|---|
| `Domain/Analyzer/Environment/ReferShadowWarner` | a `def` whose name is already `:refer`red into the namespace (#2897). Anchors on the *name* symbol: the enclosing list carries a `defn` expansion origin in `src/phel/core/defs.phel`, which stdlib suppression would silence |
| `Domain/Analyzer/Environment/ClassShadowWarner` | a `def` whose name is a loadable PHP class (#2876). Filters on a PHP-identifier regex first, because `PhpClassLike::exists()` autoloads and the stdlib runs `def` thousands of times. Warns, not refuses: refusing is breaking and belongs to the major that drops the leading `\` |

Unrelated to `Phel\Lsp\Application\Diagnostics` and `Phel\Shared\Api\Diagnostic`.

## Deprecation Warnings

`Domain/Deprecation/DeprecationWarnings` is the single process-wide switch for every compiler `E_USER_DEPRECATED`. Enabled by `--warn-deprecations` (`Console\Application\WarnDeprecationsFlag`), `PHEL_WARN_DEPRECATIONS`, or the `warn-deprecations` config key (`CompilerFactory::createAnalyzer()`). It alone owns the flag, stdlib suppression, dedup, macro-expansion attribution, syntax message shape and per-compile recording. Detectors only detect: no flag, dedup table or emitter.

| Detector | Catches |
|---|---|
| `Application/Lexer` | bare `#` comments, `#\| ... \|#` blocks, `\|()` short fns, `,`/`,@` unquote |
| `Domain/Reader/QuasiquoteTransformer` | `$` auto-gensym |
| `Domain/Analyzer/Environment/BackslashSeparatorDeprecator` | `\` namespace separator |
| `Domain/Analyzer/Environment/DeprecatedDefinitionWarner` | a resolved definition with `:deprecated` meta (`:superseded-by` names the replacement), project code too |
| `.../SpecialForm/Binding/Deconstructor/MapBindingDeconstructor` | key-first map-destructuring pairs (`{:key local}`) and ambiguous both-sides-bind pairs (`{k v}`), which stay key-first until the next major (#3115) |

Recording and gating:

- `startRecording()`/`stopRecording()` wrap `CodeCompiler::compileString()`; `EmitterResult::getDeprecations()` is stored by the compiled-code cache and raised again by `replayDeprecations()` on a hit (#3222). `replay()` skips messages this process already raised (a cold run compiles a dependency, then loads the entry it wrote).
- Detectors gate on `isDetecting()`, not `isEnabled()`: notices are *found* when the flag is on or a compile is recording, *raised* only when the flag is on, so a plain run still fills the cache for a later `--warn-deprecations` run.
- Dedup suppresses the *raise*, never the *recording*: each recording frame has its own dedup table, so a warm replay still reports (#3222).
- `withoutNotices()` (facade `withoutDeprecations()`) mutes everything for a non-compile read. Build's namespace scan needs it, or scanned files leak notices into another compile's cache entry (#3381).
- `raise()` prints paths under the working directory as relative (`Domain/Diagnostic/WorkingDirectoryPaths`) at output time; records and dedup keys stay absolute.

Raising:

- Never suppress a notice with `@`: it hides it even under `--warn-deprecations`. Call `warn()` / `warnForSource()` / `warnOnceForSource()` / `warnSyntax()`.
- `ErrorNotice::raise()` writes to stderr itself, because PHP's renderer printed each notice twice and named `ErrorNotice.php` instead of the user's source (#3262). Only with a userland error handler installed does it call `trigger_error()`, pinning `display_errors` to `stderr` for the call (skipped when display is off or already on stderr, so it never *enables* a silenced notice). Keep new notices on `raise()`: a notice raised inside the emitter's `ob_start()` was once spliced into generated PHP and broke the compile (#2827).
- `syntaxMessage()` is the only way to phrase a syntax notice and has no room for a removal version, on purpose: named versions go stale (#2783). Guarded by `DeprecationWarningsTest::test_syntax_message_has_one_shape_and_no_room_for_a_version`.
- `warnOnceForSource()` dedups per `(file, subject)` on the `realpath`-normalized file (a file reaches the analyzer under two spellings, #3262), for subjects that recur (a deprecated definition, a `\` symbol). Syntax notices do not dedup: each occurrence is a separate edit.
- `isEnabledForSource()` / `isBundledStdlibSource()` drop notices from `src/phel` or with no file. The Lexer resolves it once per source. Paths are `realpath`-normalized (memoized, only once warnings are on) so relative stdlib paths still match.

Attribution:

- `InvokeSymbol::enrichLocation()` stamps the call site onto location-less forms from a macro/inline expansion and records the definition's location as `SourceLocation::getExpansionOrigin()`. Macro arguments keep reader positions. Detectors must never report against the call site: the `\` in `(delay ...)`'s expansion lives in `src/phel/core/lazy.phel` (#2827).
- `warnOnceAtOrigin()` is the one place this rule lives (`warnSyntax()` applies it too); do not re-derive it per detector. Given a location and a `fn (string $file, int $line): string` builder, it reports an unexpanded form where it sits, an expansion against the macro's file with ` (reached by expanding a macro at <call site>)`, suppresses a stdlib origin, and stays silent on `SourceLocation::unknown()`.
- `SymbolResolver::globalVarNode()` is the single `GlobalVarNode` construction point, so each resolved definition passes the `:deprecated` check once. `GlobalEnvironment` injects both analyzer detectors into it.

`Domain/Deprecation/SupersededFormRejector` rejects `php/new`, `php/->`, `php/::` and `set-var` outright (ADR 0018, #2877, #2888). `CodeCompiler` (cold path only) and `EvalCompiler` run it on each read form; Api's `ReadAndAnalyzeStage` (lint, analyze, LSP, api-daemon) uses `CompilerFacade::rejectSupersededForms()` (#3456). The reader is the seam because macro output never passes through it. A quasiquote is already lowered there (`(apply list (concat (list (quote php/new)) …))`), so the name is quoted data, not a head; plain `quote` is skipped.
