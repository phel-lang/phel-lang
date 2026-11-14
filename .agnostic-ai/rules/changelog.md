---
name: changelog
description: Style and grouping for CHANGELOG.md release notes.
# Claude only: Codex cannot scope a root file such as CHANGELOG.md and would load this in every session. workflow.md points Codex here.
targets: [claude]
globs:
  - CHANGELOG.md
  - docs/CHANGELOG-archive.md
---

# Changelog Style

Applies to every write under `## Unreleased`: a new entry, a merge, or a rewrite. Released sections are only moved, per Archive below. The `0.52.0` section is the reference.

## Sections

In this order, each heading at most once: `### Highlights`, `### Changed`, `### Added`, `### Removed`, `### Performance`, `### Fixed`.

`### Highlights` holds at most seven one-sentence or two-sentence bullets, the changes most users must know or act on: breaking changes everyone hits, security, new guarantees. No refs or ADR numbers; the sections below carry them, so every highlight must also appear there. Revisit it whenever a bigger change lands.

Below Highlights, a section with more than four bullets across areas splits into plain sub-label lines (`Compiler:`, `Runtime:`, `Tooling:`) followed by a blank line. Not `####` headings. A caveat shared by several bullets goes once, as a sentence under the heading or label.

## Entries

- Describe what the user sees, in present tense: "`phel lint` reports a file that does not parse", "PHP 8.5 is the minimum supported version". Not "Add" or "Added".
- One to four short sentences. Lead with the change.
- A `### Fixed` bullet says what works now and leaves the old behavior to the linked issue.
- Show the code, flag or error text in backticks instead of describing it.
- One or two representative numbers for performance (`2.9x faster for two keys`), not every measurement.
- Leave out internals: implementation steps, allowlists, internal class names, test counts. Name a PHP class only when it is public API. Say when a class becomes public API.
- Breaking changes start with `**BREAKING**:` or `**BREAKING (PHP API)**:`, come first in their section, and say what to use instead.
- End with the refs: `(#3321)`, several as `(#3317 #3318)`, an ADR as `(#3311, ADR 0019)`.
- No em or en dashes. No marketing words.

## Grouping

- One bullet per theme, not per PR or per change: related changes share a bullet as a short list (`Reader: ...`, `Lazy seqs: ...`) and list every ref. A section rarely needs more than a dozen bullets.
- Keep a change in its own bullet only when it is breaking and needs its own instruction.
- A fix to something still unreleased folds into that feature's bullet. It is never a `### Fixed` entry.
- Skip changes users cannot see: `chore:`, CI, test-only, internal refactors.

Before committing, reread the whole `## Unreleased`, not only the new bullet: duplicate headings, siblings to merge, entries past four sentences, and whether Highlights still names the biggest changes.

## Archive

`CHANGELOG.md` keeps releases from the latest minor that is a multiple of ten (`0.50.0`, `0.60.0`, `1.10.0`) or major onward. After the next one ships, a follow-up PR moves every older section, unchanged, to the top of `docs/CHANGELOG-archive.md` and updates the version named in both files' intro lines. Never edit archived notes.
