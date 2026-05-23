---
name: testing-guidelines
---

PHPUnit tests live alongside fixtures in `tests/php/{Unit,Integration}`, named `*Test.php` with snake_case method names
(e.g., `test_it_does_something()`). Run `composer test` locally before every PR; when touching the compiler or runtime,
add focused unit tests plus an integration scenario. Integration test fixtures use `--PHEL--`/`--PHP--` format in
`.test` files under `tests/php/Integration/Fixtures/`.
