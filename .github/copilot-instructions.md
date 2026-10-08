# GitHub Copilot Instructions

This repository is a Laravel package: `basekit-laravel-media` — a media library for Basekit
Laravel. Uploaded files live on a Laravel filesystem disk; the package keeps the storage
metadata, lets editors organise files into a folder hierarchy through a Filament panel, and
provides a picker field any other package can embed.

It targets PHP ^8.4|^8.5 and Laravel ^13 and is distributed on Composer as
`basekit-laravel/basekit-laravel-media`. The canonical agent instructions live in
`AGENTS.md` — if you can read that file, prefer it over these instructions. This file
exists so Copilot surfaces that only read `copilot-instructions.md` (for example code
review) still follow project rules.

## Working in this repository

- This is a **Laravel package**, not a standalone Laravel application. Never assume
  application-only scaffolding such as `app/`, authentication, `.env`, or a local
  `config/app.php`.
- Filament is a *suggested* dependency. Anything outside `src/Filament/` must work in an
  application that has never installed Filament.
- Respect the Composer constraints in `composer.json` (PHP ^8.4|^8.5, Laravel ^13) and
  classify runtime vs development dependencies correctly (`require` vs `require-dev`,
  with Filament in `suggest`).
- Package APIs (public classes, methods, config keys, publish tags) are contracts — avoid
  breaking changes and document the public behavior.
- Prefer existing package patterns; implement the smallest correct change.
- Require tests for meaningful behavior changes and document their status honestly.

## Verification commands (use only those configured in composer.json)

- Tests: `composer test`
- Coverage: `composer test-coverage` (HTML report in `build/coverage`; needs a coverage
  driver, which CI provides)
- Code style: `composer lint` (check, as CI runs it) / `composer format` (apply fixes)
- Static analysis: `composer analyse` (PHPStan/Larastan)
- Refactoring: `composer refactor-dry` (report) / `composer refactor` (apply)
- Vulnerabilities: `composer audit`
- All of the above except coverage and refactoring: `composer check`

## Correctness expectations

- `Media` always uses the `SoftDeletes` trait and the table always has `deleted_at`; the
  `soft_deletes` config decides what `delete()` does with the row. Keep the schema
  unconditional. `parent::performDeleteOnModel()` is `Model`'s **hard** delete — the
  trait's own implementation is reached only through the `performSoftDeleteOnModel`
  alias.
- The `soft_deletes` key must stay out of `env()`: `env('X', false)` yields the truthy
  string `'false'`, and this key changes what deleting does to rows already in the table.
- The duplicate `disk`/`path` check belongs in a `saving` hook on the model, not in a
  unique index: a trashed row must not block re-uploading the same path, and a
  `ValidationException` is something the form can show.
- Buttons are hidden with `MediaAccess::allows()`; every action must authorize again with
  `MediaAccess::authorize()` before it changes anything — including drag and drop handlers,
  which a browser can call without touching the page. Record-level abilities pass the row.
- Inline Blade compiled inside a method has its own scope and does not inherit the
  class's imports. `MediaPicker::toEmbeddedHtml()` resolves `$canUpload` in PHP and passes
  it into the markup; do not reference `MediaAccess` inside the markup itself.
- CSS lives in `resources/css/basekit-laravel-media/*.css`, registered as a Filament asset by
  the service provider. Never put a `<style>` block in a Blade view. Class names follow BEM
  with `bkm` as prefix — `bkm-<block>`, `bkm-<block>__<element>`, `bkm-<block>--<modifier>` —
  and `bkm` alone is the prefix, never a block.
- Moving a file between folders is metadata only. Never move or rename a stored blob as
  part of filing it, or published URLs break.
- Test isolation matters: every test that writes to a disk writes under a randomized
  directory removed in `tearDown()`.

## Commits

Write a single conventional-commit subject line (`feat:`, `fix:`, `perf:`, `docs:`,
`ci:`, `chore:`) and no commit body. release-please generates the changelog from those
subjects, so keep them short and put rationale in the pull request.

## Repository guardrails

- Never modify files under `vendor/` or generated/published artifacts.
- Once a release has been installed, editing its migration does nothing for that install —
  schema changes go in a new, later-timestamped migration.
- Keep docblocks short: say what the signature cannot show and why the code is shaped this
  way. Two or three lines is usually the whole story.
- Inspect the relevant code first, search for existing equivalents before creating new
  components, and report verification results truthfully.