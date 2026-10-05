---
name: release
description: Release preparation for this Laravel package — conventional commits, release-please versioning, changelog, README alignment, migration notes, and verification before releasing.
license: MIT
compatibility: opencode
---

# Package Release

Use this skill when preparing a release for this Laravel package
(`basekit-laravel-media`, PHP ^8.4|^8.5, Laravel ^13).

## How this package releases

Releases are automated by **release-please**. On a push to `main` it opens (or updates) a
release pull request containing the version bump in `.release-please-manifest.json` and the
generated `CHANGELOG.md`; merging that pull request creates the tag. The workflow is
`.github/workflows/release-please.yml`, configured by `release-please-config.json`
(`release-type: php`, `bump-minor-pre-major: true`).

You therefore do not edit `CHANGELOG.md`, do not bump a version by hand, and do not create
a release commit. The only lever you have is the commit history.

## Writing the commits that become the changelog

One conventional-commit subject line and nothing else: `feat:`, `fix:`, `perf:`, `docs:`,
`refactor:`, `ci:` or `chore:`. Those subjects are what users read in the changelog.

- `feat:` → minor, `fix:`/`perf:`/`refactor:` → patch, a `!` or `BREAKING CHANGE:` footer → major.
- Keep the subject concrete and user-facing. "media: soft deletes configurable" beats
  "wip" or "fix bug".
- Put rationale, refactor notes and test plans in the pull request, not the commit body.

## Before a release is cut

1. **Semantic versioning**: breaking public API changes → `major`; new backward-compatible
   features → `minor`; fixes and internal changes → `patch`. Public API includes the models,
   `MediaWriter`, `MediaAccess`, `MediaPicker`, config keys and publish tags.
2. **Breaking changes** need a documented migration path in the README, and the schema
   change must be a new migration — editing an already-released migration does nothing for
   installs that have run it.
3. **README**: verify it matches the released behaviour (installation, configuration, usage,
   publish tags). Fix anything that drifted.
4. **Compatibility**: confirm the package still supports the declared PHP (`^8.4|^8.5`) and
   Laravel (`^13`) constraints.
5. **Verification**: run the full suite and static analysis and record the real results —
   `composer check` and `composer audit`, plus `composer test-coverage` where a driver is
   available. Never lower the coverage floor to make a build pass.

## Guardrails

- Never merge a release pull request whose CI is red.
- Never introduce unreviewed breaking changes into a patch release.
- `composer.lock` is ignored and must stay ignored; it is not part of a release.