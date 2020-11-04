# PHPDocs multiline

## Status

**Proposed**

## Context

I've seeing that we use PHPDoc multi-line and single-line without any apparent reason for it.

This ADR helps us to unify the PHPDoc's style in every file in this project.

## Decision

- We should write all PHPDoc blocks as **multiline**.

```php
// Bad
/** @param array $strings */
function parseStringsBad(array $strings): void
{/* ... */}

// Good
/** 
 * @param array $strings 
 */
function parseStringsGood(array $strings): void
{/* ... */}

// Better
/** 
 * @param string[] $strings 
 */
function parseStringsBetter(array $strings): void
{/* ... */}
```

- PHPDocs **doesn't need to add the typehints for the arguments/return-types 
when they are already defined** in the signature method as code.

```php
// Bad
/**
 * @return void 
 */
function fooBad(): void
{/* ... */}

// Good
// No PHPDocs needed here! Not at least to define the return type ;)
function fooGood(): void
{/* ... */}
```

## Consequences

- This will help us to follow the same style-guide everywhere in the project.
