# Phase S4 — Content: real posts, fixed pages, metadata, internal links. Paste into a fresh SONNET session, ONLY after S3 is merged.

Read `plan.md` FIRST, in full — plus §9 build log and `KNOWN-ISSUES.md`. Execute plan §6.2 under the
autonomy protocol §4. Build nothing outside the plan.

HARD LIMITS (plan §4.7): no schema/auth/router/SEO-core changes; no new URLs beyond `docs/url-map.csv`.
Content is written to `content/*.md` and imported with `bin/seed.php` — never only into SQLite.

Phase rules:
- Branch `phase/s4-content` off latest `main`. S3 unmerged ⇒ finish it first. Re-runnable: skip posts
  that already have real (non-Lorem) content ≥ 900 words.
- Write like a knowledgeable local, not a brochure. Specific places, neighbourhoods, seasons, how to
  get there, what to bring. NO invented prices, hours, phone numbers, statistics or awards — use "check
  current hours/prices" and flag anything you are unsure of as `unverified:` in the build log.
- Every post: H2/H3 structure, practical-info list, 3–5 FAQ, ≥ 2 internal links, one CTA. Every kept
  URL: unique meta title (30–60) + description (70–155). Every media row: descriptive alt text.
- Parallelise with Sonnet subagents (load `fable-cost-guardrail` first): ~6 posts per agent, each
  given the house style + a list of internal-link targets. Review each batch yourself.
- Run `bin/seo-audit.php` until every published item scores ≥ 80 and Lorem Ipsum count is 0.
- Where the scan lacks copy (FAQ answers, practical info), write it fresh — do not try to fetch the
  old site.
- Minor issues → `KNOWN-ISSUES.md`. Stop only per §4.4.

Exit: CI green; verify passes; seo-audit ≥ 80 everywhere, zero Lorem Ipsum; PR merged; §9 entry
listing all `unverified:` facts.

## After this phase — hand off to the next (fresh session)
Only when the four gates in plan §4.9 pass: call claude-code-remote `create_session` with
`model` = Sonnet (`claude-sonnet-5`), `source_url` = `https://github.com/antonmarklundcom/thingstodoinparaguay`,
`source_revision` = `main` (REQUIRED — without them the child starts with no repository checked out and
cannot find the prompt file), inherit environment and permission mode (never `plan`),
`prompt` = `Read prompts/sonnet-5-launch.md in this repo and execute it.` Then end with a phase report.
Fallback: continue in this window. Never Fable. Never hand off with a red or unmerged PR.
