# ApiFacade replComplete

## Motivation

Provide a simple way to obtain REPL autocompletion suggestions programmatically.

## Decision

Expose a new `replComplete` method through the `ApiFacadeInterface`. 
The facade uses an internal `ReplCompleter` service to return an ordered list of completion candidates for a given input string.

## Consequences

Consumers can reuse the REPL completion logic outside of the CLI tool by calling `ApiFacade::replComplete`. 
This centralizes completion capabilities under the Api module.
