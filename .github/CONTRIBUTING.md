# Contributing to Phel

## Welcome!

We look forward to your contributions! Here's how you can contribute:

* [Report a bug](https://github.com/phel-lang/phel-lang/issues/new?labels=bug&template=BUG.md)
* [Propose a new feature](https://github.com/phel-lang/phel-lang/issues/new?labels=enhancement&template=FEATURE_REQUEST.md)
* [Send a pull request](https://github.com/phel-lang/phel-lang/pulls)

For substantial changes (architecture decisions, breaking changes, etc.), please open an issue first to discuss your proposal.

## We have a Code of Conduct

Please note that this project is released with a [Contributor Code of Conduct](CODE_OF_CONDUCT.md).
By participating in this project you agree to abide by its terms.

## Any contributions you make will be under the MIT License

When you submit code changes, your submissions are understood to be under the same [MIT](https://github.com/phel-lang/phel-lang/blob/master/LICENSE) that covers the project.
By contributing to this project, you agree that your contributions will be licensed under its MIT.

## Write bug reports with detail, background, and sample code

In your bug report, please provide the following:

* A quick summary and/or background.
* Steps to reproduce:
    * Be specific!
    * Give sample code if you can.
* What you expected would happen.
* What actually happens.
* Notes (possibly including why you think this might be happening, or stuff you tried that didn't work).

Please post code and output as text ([using proper markup](https://guides.github.com/features/mastering-markdown/)).
Do not post screenshots of code or output.

## Quick Start

### Requirements

- PHP 8.2 or higher
- Composer

### Setup

1. Fork and clone the repository
2. Install dependencies: `composer install`
3. (Optional) Set up environment: `cp .env.example .env`
   - Configure `PR_RUN_AFTER_CREATION` to run commands after PR creation
   - Example: `PR_RUN_AFTER_CREATION="claude -p 'using gh update the current PR in this branch description following the template, keep it simple'"`

### Pull Request Workflow

1. Create your branch from `main`
2. Make your changes and add tests
3. Run tests: `composer test`
4. Format code: `composer fix`
5. Create PR: `composer create-pr` (or manually with `gh pr create`)
6. Ensure CI passes

Make sure you have [set up your Git username and email](https://git-scm.com/book/en/v2/Getting-Started-First-Time-Git-Setup) properly.

## Development Commands

### Essential Commands

```bash
composer test          # Run all tests (compiler + core library)
composer fix           # Auto-format code (PHP CS Fixer + Rector)
composer create-pr     # Create a pull request using the CLI tool
```

### Individual Test Suites

```bash
composer test-compiler # Run PHPUnit tests (unit + integration)
composer test-core     # Run Phel core library tests
composer psalm         # Run Psalm static analysis
composer phpstan       # Run PHPStan static analysis
```

### Git Hooks (Optional)

Enable git hooks to run tests before commits:

```bash
tools/git-hooks/init.sh
```

You can skip hooks with `git commit --no-verify` if needed, but ensure you run `composer test` before pushing.

## Testing

Phel has two test suites:

1. **PHP Compiler Tests**: PHPUnit tests for the compiler (`composer test-compiler`)
2. **Core Library Tests**: Phel's own [testing framework](https://phel-lang.org/documentation/testing/) (`composer test-core`)

Run both with `composer test`.  
