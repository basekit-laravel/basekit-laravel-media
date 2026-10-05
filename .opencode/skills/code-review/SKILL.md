---
name: code-review
description: Package review checklist — architecture, public API, compatibility, Composer, Laravel integration, database, security, testing, documentation and performance.
license: MIT
compatibility: opencode
---

# Package Code Review

Use this skill when reviewing changes to this Laravel package
(`basekit-laravel-media`, PHP ^8.4|^8.5, Laravel ^13).

## Checklist

- **Architecture**: consistent with the package structure and existing patterns; minimal scope.
- **Public API**: no unnecessary new API surface; public behaviour is documented and stable.
- **Compatibility**: within supported PHP `^8.4|^8.5` and Laravel `^13`; no use of host-app
  scaffolding; no breaking behaviour change.
- **Composer**: dependencies justified and classified (`require` vs `require-dev`, with
  optional Filament in `suggest`).
- **Laravel integration**: package discovery and provider registration correct; config,
  views, translations, migrations and factories namespaced and publishable.
- **Database**: migrations portable and forward compatible; indexes, constraints and
  factories in sync; no edit to an already-released migration.
- **Security**: input validated; **every state-changing action authorizes server-side**, not
  only the button that triggers it; no secrets or sensitive data leaked in logs, storage or
  commits.
- **Testing**: behaviour tests added or updated; tests exercise the public API and run
  cleanly; disk-writing tests stay isolated per test.
- **Documentation**: README reflects the change; upgrade notes for breakage.
- **Performance**: no unnecessary per-request or boot-time work; no N+1 or missing indexes.

## Package-specific traps

- `parent::performDeleteOnModel()` resolves to `Model`'s **hard** delete. The trait's own
  implementation is only reachable through the `performSoftDeleteOnModel` alias. Any change
  to the deletion path needs a test in both `soft_deletes` modes.
- Moving a file between folders must stay metadata-only. If a change touches the disk for a
  move or a rename, published URLs will break.
- `Media::softDeletesEnabled()` and the `soft_deletes` config key must not move to `env()`.
- Inline Blade compiled inside a method has its own scope; a class referenced in it resolves
  in the global namespace and silently evaluates to null.

## Reporting

- Inspect the actual diff and surrounding context before commenting.
- Report findings in severity order (blocking → major → minor → nit) with a location and a
  concrete suggestion for each.
- Distinguish verified facts from suggestions and never claim checks you did not run.