# Command Module

## Motivation

Describe the responsibilities of the Command module.

## Decision

This module is the place for all shared logic between the commands among different modules.

## Consequences

We have in a unified place the reading of the `phel-config.php` values: 
- source directories
- test directories
- vendor directories
- output directory

And utils for presenting command exceptions.
