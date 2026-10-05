# basekit-laravel-media

This package is a media library for Basekit Laravel: metadata for files stored on a
Laravel filesystem disk, a folder hierarchy, a Filament administrator to upload and
organise media, and a picker field any other package can embed so editors can choose an
existing file.

It targets PHP ^8.4|^8.5 and Laravel ^13 and is distributed on Composer as
`basekit-laravel/basekit-laravel-media`. All package source code lives under the
`BasekitLaravel\BasekitLaravelMedia` namespace.

## What this project is

This repository is a **Laravel package**, not a standalone Laravel application. The package is
consumed by other Laravel applications, so the code must never assume application-only
scaffolding such as `app/`, a booted authentication system, `.env` files, or a local
`config/app.php`. Everything the package needs must come from its own service provider,
configuration, and published resources.

Filament is a **suggested** dependency, not a required one. Anything outside `src/Filament/`
must work in an application that has never installed Filament.

Keep this file focused on **this package**. For framework-level knowledge, consult the official
Laravel documentation, Laravel Boost (if configured), or the available documentation MCP tools —
do not embed generic Laravel guidance here.

## Repository structure

- `src/` — package source code. The service provider (`BasekitLaravel\BasekitLaravelMedia\BasekitLaravelMediaServiceProvider`)
  is registered automatically through Composer package discovery.
- `config/basekit-laravel-media.php` — package configuration, published with
  `php artisan vendor:publish --tag="basekit-laravel-media-config"`.
- `database/migrations/` — the `media` and `media_folders` tables, loaded automatically and
  publishable with `--tag="basekit-laravel-media-migrations"`. `database/factories/` holds the
  Eloquent factories used by the tests and by consumers.
- `lang/` — translations, published with `--tag="basekit-laravel-media-translations"`. Every
  user-facing string in the Filament layer comes from here; do not hard-code English in PHP.
- `resources/views/` — Blade views exposed under the `basekit-laravel-media` view namespace
  (`view('basekit-laravel-media::view-name')`), publishable with
  `--tag="basekit-laravel-media-views"`.
- `src/Filament/` — the optional Filament layer: `MediaPlugin` (registers the screen on a
  panel), `Pages/MediaLibrary` (the screen itself), `MediaPicker` (the embeddable field),
  `MediaTableConfiguration` (the table both share) and
  `Concerns/ConfiguresMediaUpload` (the upload field both share).
- `src/Models/`, `src/Support/` — Filament-free code. `Media`, `MediaFolder`, `MediaAccess`
  and `MediaWriter` must keep working without Filament installed.
- `tests/` — Pest tests running against Orchestra Testbench (11.*). `tests/TestSupport/`
  holds the panel provider, the test user and the factory helpers. `tests/Filament/` covers
  the screens; `tests/Feature/` the models and storage behaviour; `tests/Unit/` pure logic.

## Development commands

Install dependencies with `composer install`.

### Tests

```bash
composer test
```

### Coverage

```bash
composer test-coverage
```

Writes an HTML report to `build/coverage`. CI runs this with a `--min` floor on
PHP 8.4 using pcov. **Never lower the floor to make a build pass** — raise it to
the measured baseline instead. Note that no coverage driver is installed by
default in local environments, so this command only works where xdebug or pcov
is available.

### Code style (Laravel Pint)

```bash
composer format   # apply fixes
composer lint     # check only, as CI runs it
```

### Static analysis (PHPStan / Larastan)

```bash
composer analyse
```

### Automated refactoring (Rector)

```bash
composer refactor-dry   # report only
composer refactor       # apply
```

### Dependency vulnerabilities

```bash
composer audit
```

CI fails on any advisory, including dev-only ones.

### Everything at once

```bash
composer check   # lint + analyse + test
```

## Package development

When working on this package, treat it as any other piece of distributed software: the
public API you expose today is a contract your consumers rely on.

### Configuration

Extend `config/basekit-laravel-media.php` for new options, and always read them with
`config('basekit-laravel-media.key')` using sensible defaults. New publishable groups go
through the service provider's `publishes` call with a `basekit-laravel-media-*` tag.

`soft_deletes` is deliberately **not** read from `env()`. `env('X', false)` yields the
*string* `'false'`, which is truthy, and this particular key changes what deleting does
to rows already in the table. Do not "improve" it into an environment variable.

### The deletion model

`Media` always uses the `SoftDeletes` trait and the table always has a `deleted_at`
column. A trait cannot be applied conditionally — it is fixed when the class is
compiled, not asked about at runtime — so the `soft_deletes` setting decides what
`delete()` does with the row instead. Keeping the schema unconditional is deliberate:
the setting can change with no migration.

The trait's own delete is reached through an alias:

```php
use SoftDeletes {
    performDeleteOnModel as performSoftDeleteOnModel;
}
```

A trait's methods are *copied into* the class, so `parent::performDeleteOnModel()`
does not reach the trait — it lands on `Model::performDeleteOnModel()`, which really
does delete. That compiles, passes static analysis, and silently turns every soft
delete in the library into a hard one. Keep the alias.

Two consequences worth knowing before you change any of this:

- Trashed rows are excluded from every default query, including the duplicate-path
  check. A soft-deleted row therefore does not block re-uploading the same path, which
  is exactly why the unique index was dropped in favour of the model check.
- `forceDelete()` removes the blob in both modes. `delete()` only does so when
  soft deletes are switched off.

`MediaFolder` is always deleted permanently, in both modes; a trashed folder would go on
holding files that had nowhere to be seen from. Its `media.folder_id` foreign key nulls
rather than cascades, so the files fall back to *All files*.

### Duplicate storage paths

Two media rows may not claim the same `disk` and `path`, because whichever page rendered
second would show the wrong name and alt text for the file. The check is a `saving` hook
on the model that throws a `ValidationException`, not a unique index: an index cannot
both hold a soft-deleted row and let its file be uploaded again, and it fails as an opaque
driver error instead of something the form can show.

### Authorization

`MediaAccess` resolves an ability in two steps: a policy the application registered for
`Media` or `MediaFolder` is asked first and wins, otherwise the gate name under
`basekit-laravel-media.permissions` is asked, and a `null` name allows. The permissive
default is on purpose — installing the package must never lock an existing panel out of
its own media library.

Buttons are hidden with `MediaAccess::allows()`, and every action **authorizes again**
with `MediaAccess::authorize()` before it changes anything. That includes the drag and
drop handlers, which a browser can call without touching the page. Any new action needs
both halves: a visibility check so the UI is honest, and an authorization call so the
server is the one deciding.

Record-level abilities must pass the row, so a policy gets to answer for that row.

### Filament integration

- The admin screen and the picker share `ConfiguresMediaUpload` and
  `MediaTableConfiguration` so the two cannot drift apart on where a file lands, how
  large it may be, or what the selection table looks like.
- `Filament\Forms\Components\ModalTableSelect` renders a single `Select` action and never
  its affix actions, so `MediaPicker::toEmbeddedHtml()` is overridden to render the upload
  as a labeled button beside it, and repeats it in the modal footer.
- That override builds markup with `ob_start()`. **Inline Blade markup compiled inside a
  method has its own scope and knows nothing about this class's imports**, so anything it
  needs — `$canUpload`, labels, data — must be resolved in PHP and passed in. Referencing
  a class name directly inside the markup resolves in the global namespace and silently
  returns null.
- `MediaPicker` is not backed by an Eloquent relationship, so the relationship hooks
  `ModalTableSelect` wires up are overridden to no-ops.
- The media picker stores the file's **web URL**, not its ID, so consuming models keep
  publishing the string they already stored. `resolveKey()` and `resolveStoredValue()`
  translate on the way in and out, and pass unknown values through untouched so data
  placed there before the picker existed is never lost.

### Storage

The database row and the disk file are separate concerns. The row records `disk`, `path`,
`mime_type` and `size`; `MediaWriter` fills those in from the file Filament already
stored, and deletes the previous file only when `path` actually changed. Moving a file
between folders is metadata only, so published URLs stay stable.

`MediaWriter::update()` treats an omitted `folder_id` or `alt` as "leave it alone" and
only an explicit `null` as "clear it", so a caller that came to rename a file does not also
lose where it was filed.

Uploads land in `path/folder-slug`, derived from the folder open at upload time. A folder's
slug only ever affects future uploads — renaming a folder never moves files that are
already stored.

### Migrations

The migration is loaded automatically and can also be published. Once a release has been
installed somewhere, **editing an already-released migration does nothing for that
install** — the file is already recorded in their `migrations` table. Schema changes go in
a new, later-timestamped migration.

Keep migrations portable: no engine-specific SQL, and let `constrained()` create the
foreign keys and their indexes rather than adding a redundant index on top.

### Testing

Write Pest tests under `tests/`. Use `tests/TestCase.php` when the test needs the framework
container or the filesystem, `tests/FilamentTestCase.php` for the screens and the picker, and
keep pure logic tests under `tests/Unit/`. Verify behavior from the consumer's perspective
where appropriate instead of asserting implementation details.

Every test that writes to a disk writes under a randomized directory that is deleted in
`tearDown()`. Keep it that way — a shared temp directory makes the suite order-dependent
and leaks files between tests.

## Compatibility

- Respect the Composer constraints in `composer.json`: PHP ^8.4|^8.5 and Laravel
  ^13. Do not introduce syntax, APIs, or dependencies that break the declared
  minimum versions.
- Classify dependencies correctly in `composer.json`: runtime needs go into `require`;
  development-only tooling goes into `require-dev`. Filament belongs in `suggest`
  because the package is usable without it.
- Prefer requiring interfaces and small, well-maintained packages. Avoid adding a dependency
  where a few lines of stdlib or Illuminate code suffice.
- Package APIs are contracts. Avoid breaking changes; when they are unavoidable, follow the
  release workflow and document a migration path.
- New public classes, methods, config keys, and publish tags are public API — document them
  in the README.
- `CHANGELOG.md` is generated by release-please from conventional commits. Do not
  hand-edit the released sections; write clear conventional commit messages
  (`fix:`, `feat:`, `perf:`, `docs:`) instead.

## Agent rules

These rules apply to every AI agent working in this repository:

1. **Inspect first.** Before editing anything, read the relevant package structure, related
   classes, tests, configuration, Composer constraints, and existing patterns.
2. **Search before creating.** Before creating a new class, component, or config option, look
   for an existing equivalent.
3. **Prefer existing patterns.** Follow the package's existing architecture and conventions.
4. **Minimal changes.** Implement the smallest correct change that satisfies the request.
5. **Write tests.** Every meaningful behavior change should come with tests.
6. **Run the relevant checks.** Actually run the tests/analysis documented above, and the
   targeted subset when a full run is impractical.
7. **Format your changes.** Run the configured formatter on the files you touched.
8. **Inspect your diff.** Review what you changed before reporting completion.
9. **Do not modify generated or vendor files.** Files under `vendor/`, published resources that
   are not yours, and generated artifacts must never be hand-edited.
10. **Report honestly.** Never claim "tests pass" or "analysis is clean" unless you actually ran
    the commands and they succeeded.
11. **Keep the README in sync.** A new config key, publish tag or public method needs an
    update in `README.md`.
12. **Keep docblocks short.** Say what the reader cannot see from the signature and why the
    code is shaped this way. Two or three lines is usually the whole story; a comment block
    that re-explains the method is noise.
13. **Keep commit messages short.** One conventional-commit subject line and
    nothing else: `feat:`, `fix:`, `perf:`, `docs:`, `ci:` or `chore:`. release-please
    turns these subjects into the changelog, so they are what users actually read. Put
    rationale, refactor notes and test plans in the pull request, not the commit body.