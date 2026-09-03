# Phase S3 — Design system, public templates, performance. Paste into a fresh SONNET session, ONLY after O2 is merged.

Read `plan.md` FIRST, in full — plus §9 build log and `KNOWN-ISSUES.md`. Execute plan §6.1 under the
autonomy protocol §4. Build nothing outside the plan.

HARD LIMITS (plan §4.7): no schema changes, no auth changes, no changes to `src/Router*`, `src/Seo*`,
`docs/url-map.csv` or the redirect behaviour. Data only via the `src/Repo*` classes. Need something
else? Work around it and add a §10 Backlog note.

Phase rules:
- Branch `phase/s3-design` off latest `main`. O2 unmerged ⇒ finish it first. Re-runnable.
- Load skills: `web-design-system` (if present), `higgsfield-web-imagery` (images, respect budget),
  `vendercrm-lead-capture` (contact form), `fable-cost-guardrail` (before any subagent).
- Mobile-first, one CSS file, no framework, no external font/CDN requests. Keep O1's semantic markup
  and SEO output intact — verify still passes after every template change.
- Measure Lighthouse (mobile) with the pre-installed Chromium/Lighthouse on `/`, a post, a tour and
  `/blog/`; iterate until ≥ 95 on all four categories; write the numbers in the build log.
- Forms: validate server-side, honeypot + time-trap, store leads/subscribers in SQLite, email via env
  SMTP or `mail()`, VenderCRM push only when env is set. WhatsApp button on every page.
- Minor issues → `KNOWN-ISSUES.md`. Stop only per §4.4.

Exit: CI green; verify passes; Lighthouse ≥ 95 ×4 on the four URLs; W3C-valid HTML on them; contact
form test (`tests/forms.test.php`) passes; PR merged; §9 entry.

## After this phase — hand off to the next (fresh session)
Only when the four gates in plan §4.9 pass: call claude-code-remote `create_session` with
`model` = Sonnet (`claude-sonnet-5`), `source_url` = `https://github.com/antonmarklundcom/thingstodoinparaguay`,
`source_revision` = `main` (REQUIRED — without them the child starts with no repository checked out and
cannot find the prompt file), inherit environment and permission mode (never `plan`),
`prompt` = `Read prompts/sonnet-4-content.md in this repo and execute it.` Then end with a phase report.
Fallback: continue in this window. Never Fable. Never hand off with a red or unmerged PR.
