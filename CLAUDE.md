# Claude Code Instructions

The canonical project instructions for this package live in `AGENTS.md` and always take
precedence over anything in this file.

@AGENTS.md

## Claude Code specifics

- The canonical instructions for this package are in `AGENTS.md` — if it is present, read it
  before starting work and follow it.
- Follow the "Agent rules" section of `AGENTS.md` in every session.
- Run the package's configured verification commands exactly as documented in `AGENTS.md`
  and report the real results.
- Never modify files under `vendor/` or generated/published artifacts.
- Prefer the package's existing patterns over introducing new ones; keep changes minimal.
- The verification commands are `composer lint`, `composer analyse`, `composer test`,
  `composer test-coverage` and `composer audit`. `composer check` runs lint, analyse and
  test together.
- `Media` always carries the `SoftDeletes` trait and the `soft_deletes` config decides what
  `delete()` does with the row. Do not make the trait or the `deleted_at` column
  conditional, and never reintroduce `parent::performDeleteOnModel()` — that is
  `Model`'s hard delete. Say so in your summary if you touch the deletion path.
- Every action that changes something must authorize server-side with `MediaAccess::authorize()`;
  `MediaAccess::allows()` alone only hides a button. A new action needs both halves.
- CSS lives in `resources/css/basekit-laravel-media/*.css`, registered by the service provider as
  a Filament asset — never inline a `<style>` block in a Blade view. Class names follow BEM with
  `bkm` as prefix: `bkm-<block>`, `bkm-<block>__<element>`, `bkm-<block>--<modifier>`. `bkm` on
  its own is the prefix, never a block.
- Write commit messages as a single conventional-commit subject line (`feat:`, `fix:`,
  `perf:`, `docs:`, `ci:`, `chore:`) with no body. release-please uses those subjects for
  the changelog; put rationale in the pull request instead.