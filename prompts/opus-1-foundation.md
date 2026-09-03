# Phase O1 — Foundation, SEO core, URL contract, migration tooling. Paste into a fresh OPUS session.

Read `plan.md` FIRST, in full — plus §9 build log and `KNOWN-ISSUES.md`. Then `docs/wp-scan-brief.md`
and `docs/url-map.csv`. Execute plan §5.1 under the autonomy protocol §4. Build nothing outside the plan.

Phase rules:
- Branch `phase/o1-foundation` off latest `main`. Re-runnable: check what exists first.
- Plain PHP 8.2+, no framework, no runtime Composer. Vendor Parsedown as a single file with its licence.
- Write `db/schema.sql` for the COMPLETE §2 object model now, even tables O1 does not use yet.
- Do NOT build or run any WordPress API export. Use `bin/scan-import.php` over `docs/wp-scan.md` as
  the only content source. If `bin/wp-export.php` exists on the branch, delete it and remove
  references (README, build log).
- Every route emits the full SEO layer (§1.9). Templates may be unstyled; markup must be final-quality
  semantic HTML (one `<h1>`, landmarks, breadcrumbs) because S3 only skins it.
- `bin/verify.php` is the contract test: it must import `docs/url-map.csv`, boot `php -S`, and assert
  every row. Make it fast (< 60 s) and make CI run it. Load the `fable-cost-guardrail` skill before
  spawning any subagent; subagents run on Sonnet at most.
- Minor issues → `KNOWN-ISSUES.md`. Stop only per §4.4.

Exit: CI green on the PR; `bin/verify.php` passes all rows; `sitemap.xml`, `robots.txt`, `feed.xml`
served; seed is idempotent (run twice, no duplicates); README covers local dev; PR merged; §9 entry.

## After this phase — hand off to the next (fresh session)
Only when all four gates in plan §4.9 pass (merged green, exit met, pre-handoff audit done, build log
committed): call claude-code-remote `create_session` with `model` = Opus, inherit environment and
permission mode (never `plan`), `prompt` = `Read prompts/opus-2-admin.md in this repo and execute it.`
Then end with a phase report. Fallback without `create_session`: continue in this window (same model).
Never hand off with a red or unmerged PR.
