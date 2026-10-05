---
name: testing
description: Package testing conventions for Pest and Orchestra Testbench — how to test a Laravel package from the consumer's perspective, including Filament screens and disk isolation.
license: MIT
compatibility: opencode
---

# Package Testing

Use this skill when writing or running tests for this Laravel package
(`basekit-laravel-media`, PHP ^8.4|^8.5, Laravel ^13).

## Pest + Orchestra Testbench

- Tests that need the Laravel framework (container, config, migrations, storage, views)
  extend `tests/TestCase.php`, which boots the package's service provider via
  `Orchestra\Testbench\TestCase` on an in-memory SQLite connection.
- The Filament screens and the picker need an authenticated panel user, so they extend
  `tests/FilamentTestCase.php`, which registers a panel provider and signs in a test user.
  Render forms with the `tests/TestSupport/Livewire/MediaPickerForm.php` harness rather
  than inventing a host form.
- Pure logic can be tested with plain Pest functions under `tests/Unit/` — no framework
  needed.

## Conventions

- `tests/Feature/` — behaviour tests from the consumer's perspective: the models, storage
  behaviour, `MediaWriter`, and what the migrations produce.
- `tests/Unit/` — fast, dependency-free tests for pure logic.
- `tests/Filament/` — the admin screen, the picker field and authorization.
- Name tests by the behaviour they lock in (e.g. "it keeps the file when a delete is soft").
- Add a regression test alongside any bug fix that reproduces the reported issue.

## Disk isolation

Every test that writes to a disk writes under a randomized directory, configured in
`setUp()` and deleted in `tearDown()`. Keep it that way: a shared temp directory makes the
suite order-dependent and leaks files between tests. Assert on the disk through
`Storage::fake()` or the fake disk's `assertExists`/`assertMissing` rather than on the
filesystem directly.

## Testing both deletion modes

`soft_deletes` changes what `delete()` does with the row and the blob. Any behaviour around
deletion has to be covered in both modes — set the config in the test, don't assume the
default. The same applies to an authorization test: check that the action is refused
*and* that it really did not change anything.

## Running tests

```bash
composer test            # full Pest suite
composer test-coverage   # HTML report in build/coverage (needs xdebug or pcov)
```

No coverage driver is installed by default in a local environment, so
`composer test-coverage` only works where one is available; CI runs it with pcov and a
`--min` floor passed from the workflow.

Run the targeted subset while iterating, then the full suite before finishing. Report the
actual output — never claim passing tests you did not run.

## Verification expectations

Every meaningful behaviour change must include tests. When CI and static analysis are
configured for the package, run those too and report real results.