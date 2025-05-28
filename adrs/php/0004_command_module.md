# Command Module

## Motivation

Describe the responsibilities of the Command module.

## Decision

This module hosts the logic shared by commands across different modules.

## Consequences

We have a unified place for reading the `phel-config.php` values:
- source directories
- test directories
- vendor directories
- output directory

And utils for presenting command exceptions.
