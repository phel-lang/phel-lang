<p align="center">
  <a href="https://phel-lang.org/" title="Phel Lang Website">
    <img src="logo_readme.svg" width="350" alt="Phel logo"/>
  </a>
</p>

<p align="center">
  <a href="https://github.com/phel-lang/phel-lang/actions">
    <img src="https://github.com/phel-lang/phel-lang/workflows/CI/badge.svg" alt="GitHub Build Status">
  </a>
  <a href="https://scrutinizer-ci.com/g/phel-lang/phel-lang/?branch=master">
    <img src="https://scrutinizer-ci.com/g/phel-lang/phel-lang/badges/quality-score.png?b=master" alt="Scrutinizer Code Quality">
  </a>
  <a href="https://scrutinizer-ci.com/g/phel-lang/phel-lang/?branch=master">
    <img src="https://scrutinizer-ci.com/g/phel-lang/phel-lang/badges/coverage.png?b=master" alt="Scrutinizer Code Coverage">
  </a>
  <a href="https://shepherd.dev/github/phel-lang/phel-lang">
    <img src="https://shepherd.dev/github/phel-lang/phel-lang/coverage.svg" alt="Psalm Type-coverage Status">
  </a>
  <a href="https://gitter.im/phel-lang/community?utm_source=badge&amp;utm_medium=badge&amp;utm_campaign=pr-badge">
    <img src="https://badges.gitter.im/Join%20Chat.svg" alt="Gitter">
  </a>
</p>

#

Phel is a functional programming language that compiles to PHP. It is a dialect of Lisp inspired by [Clojure](https://clojure.org/) and [Janet](https://janet-lang.org/).

## Documentation

The documentation for Phel can be found on Phel's website

[Read the documentation](https://phel-lang.org)

## Community

Feel free to ask questions and join discussions on the [Phel Gitter channel](https://gitter.im/phel-lang/community).

## Contribute

You are more than welcome to contribute to Phel. You can do so by either:

* reporting bugs
* contributing changes
* enrich the documentation

### Substantial changes

Substantial changes are architecture decisions, documentation restructuring, breaking changes, etc.
But not Bug Reports, Bug Fixes, Unit Tests, etc.

#### How to contribute a substantial change

In order to make a substantial change it is a good practice to discuss the idea before implementing it.

- An Architecture Decision Record (ADR) or Request for Comments (RFC) can be proposed with an issue.
- The issue is the place to discuss everything.
- The result of the issue can be an ADR file (under the [adrs](./adrs) directory), but also just as CS Fixer rule to check then during CI.

## Development

### Requirements

Phel requires PHP 7.4 or higher and Composer.

### Testing

Phel has two test suites. The first test suite runs PHPUnit to test the compiler itself. The second test suite runs tests against Phel's core library.

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
