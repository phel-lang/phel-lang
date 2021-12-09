# Contributing to Phel

## Welcome!

We look forward to your contributions! Here are some examples how you can contribute:

* [Report a bug](https://github.com/phel-lang/phel-lang/issues/new?labels=bug&template=BUG.md)
* [Propose a new feature](https://github.com/phel-lang/phel-lang/issues/new?labels=enhancement&template=FEATURE_REQUEST.md)
* [Send a pull request](https://github.com/phel-lang/phel-lang/pulls)

### Substantial changes

Substantial changes are architecture decisions, documentation restructuring, breaking changes, etc.
But not Bug Reports, Bug Fixes, Unit Tests, etc.

#### How to contribute a substantial change

In order to make a substantial change it is a good practice to discuss the idea before implementing it.

- An Architecture Decision Record (ADR) or Request for Comments (RFC) can be proposed with an issue.
- The issue is the place to discuss everything.
- The result of the issue can be an ADR file (under the [adrs](../adrs) directory), but also just as CS Fixer rule to check then during CI.

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

## Workflow for Pull Requests

1. Fork the repository.
2. Create your branch from `master` if you plan to implement new functionality or change existing code significantly;
   create your branch from the oldest branch that is affected by the bug if you plan to fix a bug.
3. Implement your change and add tests for it.
4. Ensure the test suite passes.
5. Ensure the code complies with our coding guidelines (see below).
6. Send that pull request!

Please make sure you have [set up your username and email address](https://git-scm.com/book/en/v2/Getting-Started-First-Time-Git-Setup) for use with Git. 
Strings such as `silly nick name <root@localhost>` look really stupid in the commit history of a project.

## Coding Guidelines

This project comes with a configuration file (located at `/psalm.xml` in the repository) that you can use to perform static analysis (with a focus on type checking):

```bash
$ ./vendor/bin/psalm
```

This project comes with a configuration file (located at `/.php_cs.dist` in the repository) that you can use to (re)format your source code for compliance with this project's coding guidelines:

```bash
$ ./vendor/bin/php-cs-fixer fix
```

Please understand that we will not accept a pull request when its changes violate this project's coding guidelines.

## Development

### Requirements

Phel requires PHP 7.4 or higher and Composer.

### Running Phel's test suites

Phel has two test suites. The first test suite runs PHPUnit to test the compiler itself. The second test suite runs tests against Phel's core library.

#### Testing the PHP compiler

Phel uses PHPUnit to test its compiler.

```bash
$ vendor/bin/phpunit --testsuite unit
$ vendor/bin/phpunit --testsuite integration
```

#### Testing the core library

Phel has its own [testing framework](https://phel-lang.org/documentation/testing/).

```bash
./phel test
```

### Coding Guidelines and Tests

These are the composer scripts that might help you to run the all test suites:

```bash
composer psalm         # Run Psalm
> vendor/bin/psalm

composer test-compiler # test the compiler
> vendor/bin/phpunit --testsuite unit
> vendor/bin/phpunit --testsuite integration

composer test-core     # test core library
> ./phel test

composer test-all      # csrun, psalm, compiler & core tests after each other
> composer csrun
> composer psalm
> composer test-compiler
> composer test-core
```

### Git Hooks

Enable the git hooks with `./tools/git-hooks/init.sh`
