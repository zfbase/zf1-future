# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

zf1-future is a community fork of Zend Framework 1 whose sole goal is keeping ZF1 working on modern PHP. It must stay compatible with PHP 7.1 (`composer.json` requires `php >=7.1`) while fixing errors and deprecations on newer versions (recent work targets PHP 8.4/8.5). Most changes are compatibility fixes, not new features. Breaking changes (starting from 1.24.0) are tracked in `BREAKING-CHANGES.md`; release notes go in `CHANGELOG.md`. The version is set in `composer.json` (`"version"`).

Note: `CONTRIBUTING.md`, `DEVELOPMENT_README.md` (Vagrant/php-build setup) and `README-GIT.md` are leftovers from the original Zend project and are outdated.

## Commands

Composer installs binaries into `bin/` (not `vendor/bin`), per `config.bin-dir`.

```sh
composer install
composer lint                 # parallel-lint syntax check of the whole tree
composer test                 # full PHPUnit suite (tests/Zend/AllTests.php)
composer phpstan              # level 5 on library/, with baseline
composer phpstan:baseline     # regenerate .phpstan.dist.baseline.neon
composer php-cs-fixer:test    # / php-cs-fixer:fix
composer rector:test          # / rector:fix
```

CI (`.github/workflows/phpunit.yml`) runs `bin/phpunit -c tests/phpunit.xml` on PHP 7.1–8.3, once with minimal extensions and once with a full extension set (memcached, apcu, ldap, pdo_*, etc.).

Running a subset of tests (tests run from the repo root with the config file):

```sh
bin/phpunit -c tests/phpunit.xml tests/Zend/Acl/AclTest.php          # one test file
bin/phpunit -c tests/phpunit.xml tests/Zend/Cache/AllTests.php       # one component
bin/phpunit -c tests/phpunit.xml --filter testMethodName tests/Zend/Acl/AclTest.php
```

`tests/phpunit.xml` converts notices and warnings into exceptions, so a PHP deprecation/notice in library code fails tests.

### Test configuration

`tests/TestHelper.php` (bootstrap) prepends `library/` and `tests/` to the include path and loads `tests/TestConfiguration.php` if present, else `tests/TestConfiguration.dist.php`. The dist file defines `TESTS_ZEND_*` constants that disable tests needing external services (databases, memcached, LDAP, APC, online services) by default. To enable them locally, copy `TestConfiguration.dist.php` to `TestConfiguration.php` and edit, or copy `TestConfiguration.env.php` (what CI does), which reads the same constants from environment variables.

## Architecture and conventions

- `library/Zend/` holds all framework code, organized as PEAR-style, non-namespaced classes: `Zend_Foo_Bar` lives in `library/Zend/Foo/Bar.php` (PSR-0 autoload for `Zend_` plus `include-path: library/`). Components are largely independent (`Zend_Db`, `Zend_Controller`, `Zend_Form`, `Zend_Cache`, ...), each typically with a top-level facade/factory file (`Zend/Cache.php`) next to its directory.
- Library files load dependencies with explicit `require_once 'Zend/...php'` relative to the include path rather than relying on autoloading. Keep this pattern when adding dependencies.
- Code must parse and run on PHP 7.1: no typed properties, union types, `match`, named arguments, enums, etc. Missing newer-PHP functions are covered by the symfony polyfills (ctype, mbstring, php81, php83). Rector (`.rector.php`) is pinned to `PhpVersion::PHP_71` and deliberately skips many modernization rules — don't manually apply those rewrites either.
- php-cs-fixer rules are minimal (short arrays, `&&`/`||` over `and`/`or`, casts instead of `intval()`-style calls, explicit nullable types for `= null` defaults). Otherwise follow the surrounding ZF1 style (`_protected` member prefixes, docblocks with `@category`/`@package`).
- PHPStan excludes `library/Zend/Test/` and a few files; new issues should be fixed rather than added to the baseline where practical.

### Tests

- `tests/Zend/` mirrors `library/Zend/`. Test classes are named `Zend_<Component>_<Name>Test`, extend `Yoast\PHPUnitPolyfills\TestCases\TestCase`, and use the polyfill snake_case fixtures (`set_up()`, `tear_down()`) so one test suite runs on PHPUnit 7–9.
- Suites are aggregated manually: each component has an `AllTests.php` that `require_once`s its test files and adds them to a `TestSuite`, and `tests/Zend/AllTests.php` includes those. A new test file must be registered in its component's `AllTests.php` to run under `composer test`.
- Fixtures live in per-component `_files/` directories. `tests/Zend/Loader/_files/ParseError.php` is intentionally invalid PHP and is excluded from lint, cs-fixer and rector.
