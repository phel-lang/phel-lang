---
name: module-compiler-diagnostics
description: 'Load when adding or changing a compiler warning or deprecation notice: channels, detectors, gating and attribution.'
scope: src/php/Compiler/Domain/Diagnostic
globs:
  - src/php/Compiler/Domain/Deprecation/**
---

# Compiler Diagnostics

Read `.agnostic-ai/rules/module-compiler.md` for the pipeline and constraints. Paths here are relative to `src/php/Compiler/`, since the rule spans several folders. Unrelated to `Phel\Lsp\Application\Diagnostics` and `Phel\Shared\Api\Diagnostic`.

## Channels

Both channels raise through `Domain/Diagnostic/ErrorNotice::raise($message, $level)`, which writes one stderr line prefixed `deprecated: ` or `warning: ` (ADR 0017). They differ only in the gate.

| Channel | Level | Gate |
|---|---|---|
| `Domain/Deprecation/DeprecationWarnings` | `E_USER_DEPRECATED` | off unless `--warn-deprecations` (ADR 0006) |
| `Domain/Diagnostic/CompilerWarnings` | `E_USER_WARNING` | always on |

## Compiler warnings

`CompilerWarnings` is for diagnostics that already changed program behavior, so silence is the bug. It owns bundled-stdlib suppression and per-`(file, subject)` dedup; call `reset()` in test `tearDown()`. Both detectors run from `DefSymbol` before `addDefinition`, so they warn once per `def`, not per call site.

| Detector | Catches |
|---|---|
| `Domain/Analyzer/Environment/ReferShadowWarner` | a `def` whose name is already `:refer`red. Anchors on the name symbol: the enclosing list carries a `defn` expansion origin in `src/phel/core/defs.phel`, which stdlib suppression would silence |
| `Domain/Analyzer/Environment/ClassShadowWarner` | a `def` whose name is a loadable PHP class. Filters on a PHP-identifier regex first, because `PhpClassLike::exists()` autoloads and the stdlib runs `def` thousands of times. Refusing instead would be a breaking change |

## Deprecation warnings

`DeprecationWarnings` is the one process-wide switch for every compiler `E_USER_DEPRECATED`: `--warn-deprecations`, `PHEL_WARN_DEPRECATIONS` or the `warn-deprecations` config key (`CompilerFactory::createAnalyzer()`). It alone owns the flag, stdlib suppression, dedup, macro-expansion attribution, syntax message shape and per-compile recording. Detectors only detect.

| Detector | Catches |
|---|---|
| `Application/Lexer` | bare `#` comments, `#\| ... \|#` blocks, `\|()` short fns, `,`/`,@` unquote |
| `Domain/Reader/QuasiquoteTransformer` | `$` auto-gensym |
| `Domain/Analyzer/Environment/BackslashSeparatorDeprecator` | `\` namespace separator |
| `Domain/Analyzer/Environment/DeprecatedDefinitionWarner` | a resolved definition with `:deprecated` meta (`:superseded-by` names the replacement), project code too |
| `.../SpecialForm/Binding/Deconstructor/MapBindingDeconstructor` | key-first map-destructuring pairs (`{:key local}`) and ambiguous both-sides-bind pairs (`{k v}`), which stay key-first until the next major |

Recording and gating:

- `startRecording()`/`stopRecording()` wrap `CodeCompiler::compileString()`. The compiled-code cache stores `EmitterResult::getDeprecations()`, and `replayDeprecations()` raises them again on a hit; `replay()` skips messages this process already raised.
- Detectors gate on `isDetecting()`, not `isEnabled()`: notices are found when the flag is on or a compile is recording, raised only when the flag is on. A plain run still fills the cache for a later `--warn-deprecations` run.
- Dedup suppresses the raise, never the recording: each recording frame has its own dedup table, so a warm replay still reports.
- `withoutNotices()` (facade `withoutDeprecations()`) mutes everything for a non-compile read. Build's namespace scan needs it, or scanned files leak notices into another compile's cache entry.
- `raise()` prints paths under the working directory as relative (`WorkingDirectoryPaths`); records and dedup keys stay absolute.

Raising:

- Never suppress a notice with `@`: it hides it even under `--warn-deprecations`. Call `warn()`, `warnForSource()`, `warnOnceForSource()` or `warnSyntax()`.
- `ErrorNotice::raise()` writes to stderr itself; PHP's renderer printed each notice twice and named `ErrorNotice.php` instead of the user's source. Only with a userland error handler does it call `trigger_error()`, pinning `display_errors` to `stderr` for the call (never enabling a silenced notice). Keep new notices on `raise()`: a notice raised inside the emitter's `ob_start()` once landed in generated PHP and broke the compile.
- `syntaxMessage()` is the only way to phrase a syntax notice and has no room for a removal version, because named versions go stale (`DeprecationWarningsTest::test_syntax_message_has_one_shape_and_no_room_for_a_version`).
- `warnOnceForSource()` dedups per `(file, subject)` on the `realpath` of the file, since a file reaches the analyzer under two spellings. Use it for recurring subjects (a deprecated definition, a `\` symbol). Syntax notices do not dedup: each occurrence is a separate edit.
- `isEnabledForSource()` / `isBundledStdlibSource()` drop notices from `src/phel` or with no file. Paths are `realpath`-normalized (memoized, only once warnings are on) so relative stdlib paths match.

Attribution:

- `InvokeSymbol::enrichLocation()` stamps the call site onto location-less forms from a macro or inline expansion and records the definition's location as `SourceLocation::getExpansionOrigin()`. Macro arguments keep reader positions. Detectors must never report against the call site: the `\` in `(delay ...)`'s expansion lives in `src/phel/core/lazy.phel`.
- `warnOnceAtOrigin()` is the one place for this rule (`warnSyntax()` applies it too). It reports an unexpanded form where it sits, an expansion against the macro's file with ` (reached by expanding a macro at <call site>)`, suppresses a stdlib origin, and stays silent on `SourceLocation::unknown()`.
- `SymbolResolver::globalVarNode()` is the one `GlobalVarNode` construction point, so each resolved definition passes the `:deprecated` check once.

## Superseded forms

`Domain/Deprecation/SupersededFormRejector` rejects `php/new`, `php/->`, `php/::` and `set-var` outright (ADR 0018). `CodeCompiler` (cold path only) and `EvalCompiler` run it on each read form; Api's `ReadAndAnalyzeStage` (lint, analyze, LSP, api-daemon) calls `CompilerFacade::rejectSupersededForms()`. The reader is the seam because macro output never passes through it. A quasiquote is already lowered there, so the name is quoted data, not a head; plain `quote` is skipped.
