---
name: package-development
description: How to develop Laravel packages correctly — service providers, Composer, package discovery, config, migrations, views, translations, publishable resources and testing.
license: MIT
compatibility: opencode
---

# Laravel Package Development

Use this skill when implementing or changing any part of this Laravel package
(`basekit-laravel-media`, PHP ^8.4|^8.5, Laravel ^13).

## Core principles

- A Laravel **package** is consumed inside other Laravel applications. Never assume
  application-only scaffolding (`app/`, auth, `.env`, local `config/app.php`, consumer views).
- The service provider is the package's entry point. It is auto-discovered through Composer's
  `extra.laravel.providers`.
- Every public class, method, config key and publish tag is part of a contract consumers
  depend on. Keep the public API small and stable.

## Service provider

- `register()`: merge config (`mergeConfigFrom`) — no side effects.
- `boot()`: load migrations, views and translations, and declare what can be published.
- Publishing tags are namespaced: `basekit-laravel-media-config`,
  `basekit-laravel-media-migrations`, `basekit-laravel-media-translations`,
  `basekit-laravel-media-views`.

## Composer

- Runtime dependencies go in `require`; development tooling in `require-dev`; optional
  integrations in `suggest`.
- Respect the declared minimum PHP (`^8.4|^8.5`) and Laravel (`^13`).
- `composer.lock` is deliberately ignored: a library records its range of dependencies, not
  one resolution of them.

## Configuration

- Provide defaults under `config/basekit-laravel-media.php`.
- Read values as `config('basekit-laravel-media.key', $default)` — consumers may override.
- `mergeConfigFrom` in the provider guarantees defaults exist before the consumer publishes.
- A key that changes what happens to data already stored (`soft_deletes`) is read from the
  config file, not from `env()`: `env('X', false)` returns the truthy string `'false'`.

## Migrations and factories

- Ship migrations in `database/migrations/`. They are loaded automatically and can also be
  published; consumers run them on their own connection.
- Keep column types and constraints portable — no engine-specific SQL — and let
  `constrained()` create the foreign keys and their indexes.
- A published, already-released migration cannot be edited for installs that have run it;
  schema changes go in a new, later-timestamped migration.
- Ship factories in `database/factories/` and map them with `newFactory()`, so consumers
  and the test suite build the same rows.

## Views and translations

- Register views with `$this->loadViewsFrom(..., 'basekit-laravel-media')`; reference them as
  `view('basekit-laravel-media::name')`.
- Register translations with `$this->loadTranslationsFrom(..., 'basekit-laravel-media')` and
  reference them as `__('basekit-laravel-media::basekit-laravel-media.key')`. Every
  user-facing string in the Filament layer comes from there — no hard-coded English in PHP.
- Views must render standalone and never assume the consumer's layout.

## Storage

- The database row and the blob are separate concerns. The row records `disk`, `path`,
  `mime_type` and `size`; the file lives on the configured disk so it can be served
  directly.
- Uploads are written by Filament before the form is saved; the package only fills in the
  metadata afterwards.
- Move and rename operations that change `path` may delete the previous file; operations
  that change `folder_id` never touch the disk.

## Testing

- Use the package `TestCase` (Orchestra Testbench) for anything touching the framework or
  the filesystem, and `FilamentTestCase` for the screens and the picker.
- Keep unit tests fast and dependency-free under `tests/Unit/`.
- Give every test that writes to a disk its own randomized directory and delete it in
  `tearDown()`.

## Verification checklist

1. Read the existing package structure and patterns first.
2. Implement the smallest correct change.
3. Add or update tests for meaningful behaviour.
4. Run the relevant checks and report what you actually ran.