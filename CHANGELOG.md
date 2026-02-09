# Changelog

All notable changes to `ping` will be documented in this file.

## 1.2.1 - 2026-02-09

### What's Changed

#### Bug Fixes

- Fix macOS ping by skipping `-4`/`-6` flags (not supported by macOS `ping` command)

#### Maintenance

- Upgrade pestphp/pest from ^3.0 to ^4.3
- Remove deprecated `setAccessible()` calls in tests (PHP 8.5)
- Add PHP 8.5 to CI test matrix
- Add `skipOnGitHubActions` to tests that perform real pings

## 1.2.0 - 2026-02-01

### What's Changed

* Update issue template by @AlexVanderbist in https://github.com/spatie/ping/pull/9
* Bump dependabot/fetch-metadata from 2.4.0 to 2.5.0 by @dependabot[bot] in https://github.com/spatie/ping/pull/18
* Bump actions/checkout from 4 to 6 by @dependabot[bot] in https://github.com/spatie/ping/pull/14
* Bump stefanzweifel/git-auto-commit-action from 6 to 7 by @dependabot[bot] in https://github.com/spatie/ping/pull/11
* Add support for forcing IPv4 and IPv6 by @alexjustesen in https://github.com/spatie/ping/pull/15
* Added Symfony 8 support to all symfony/* packages. by @thecaliskan in https://github.com/spatie/ping/pull/13

### New Contributors

* @AlexVanderbist made their first contribution in https://github.com/spatie/ping/pull/9
* @alexjustesen made their first contribution in https://github.com/spatie/ping/pull/15
* @thecaliskan made their first contribution in https://github.com/spatie/ping/pull/13

**Full Changelog**: https://github.com/spatie/ping/compare/1.1.1...1.2.0

## 1.1.1 - 2025-08-12

### What's Changed

* Bump aglipanci/laravel-pint-action from 2.5 to 2.6 by @dependabot[bot] in https://github.com/spatie/ping/pull/3

**Full Changelog**: https://github.com/spatie/ping/compare/1.1.0...1.1.1

## 1.1.0 - 2025-07-20

**Full Changelog**: https://github.com/spatie/ping/compare/1.0.1...1.1.0

## 1.0.1 - 2025-07-16

**Full Changelog**: https://github.com/spatie/ping/compare/0.0.6...1.0.1

## 1.0.0 - 2025-07-14

**Full Changelog**: https://github.com/spatie/ping/compare/0.0.6...1.0.0

## 0.0.6 - 2025-07-13

**Full Changelog**: https://github.com/spatie/ping/compare/0.0.5...0.0.6

## 0.0.5 - 2025-07-13

**Full Changelog**: https://github.com/spatie/ping/compare/0.0.4...0.0.5

## 0.0.4 - 2025-07-06

**Full Changelog**: https://github.com/spatie/ping/compare/0.0.2...0.0.4

## 0.0.3 - 2025-07-02

**Full Changelog**: https://github.com/spatie/ping/compare/0.0.2...0.0.3

## 0.0.2 - 2025-07-02

### What's Changed

* Bump stefanzweifel/git-auto-commit-action from 5 to 6 by @dependabot in https://github.com/spatie/ping/pull/1

### New Contributors

* @dependabot made their first contribution in https://github.com/spatie/ping/pull/1

**Full Changelog**: https://github.com/spatie/ping/compare/0.0.1...0.0.2

## 0.0.1 - 2025-07-02

**Full Changelog**: https://github.com/spatie/ping/commits/0.0.1
