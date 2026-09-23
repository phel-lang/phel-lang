---
name: changelog
description: Style and grouping for CHANGELOG.md release notes.
globs: CHANGELOG.md
---

# Changelog Style

Applies to every write under `## Unreleased`: a new entry, a merge, or a rewrite. The `0.52.0` section is the reference.

## Sections

In this order, each heading at most once: `### Changed`, `### Added`, `### Removed`, `### Performance`, `### Fixed`.

A section with more than four bullets across areas splits into plain sub-label lines (`Compiler:`, `Runtime:`, `Tooling:`) followed by a blank line. Not `####` headings. A caveat shared by several bullets goes once, as a sentence under the heading or label.

## Entries

- Describe what the user sees, in present tense: "`phel lint` reports a file that does not parse", "PHP 8.5 is the minimum supported version". Not "Add" or "Added".
- One to four short sentences, one idea each. Lead with the change. For a fix, add the old behavior in one sentence ("It used to ...").
- Show the code, flag or error text in backticks instead of describing it.
- One or two representative numbers for performance (`2.9x faster for two keys`), not every measurement.
- Leave out internals: implementation steps, allowlists, internal class names, test counts. Name a PHP class only when it is public API. Say when a class becomes public API.
- Breaking changes start with `**BREAKING**:` or `**BREAKING (PHP API)**:`, come first in their section, and say what to use instead.
- End with the refs: `(#3321)`, several as `(#3317 #3318)`, an ADR as `(#3311, ADR 0019)`.
- No em or en dashes. No marketing words.

## Grouping

- One bullet per user-visible change, not per PR. PRs that touch the same thing share a bullet and list every ref.
- A fix to something still unreleased folds into that feature's bullet. It is never a `### Fixed` entry.
- Skip changes users cannot see: `chore:`, CI, test-only, internal refactors.

Before committing, reread the whole `## Unreleased`, not only the new bullet: duplicate headings, siblings to merge, entries past four sentences.
