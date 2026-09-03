# Phase O2 — Admin panel & publishing pipeline. Paste into a fresh OPUS session, ONLY after O1 is merged.

Read `plan.md` FIRST, in full — plus §9 build log and `KNOWN-ISSUES.md`. Execute plan §5.2 under the
autonomy protocol §4. Build nothing outside the plan.

Phase rules:
- Branch `phase/o2-admin` off latest `main`. O1 unmerged ⇒ finish it first. Re-runnable.
- Security is the quality bar: bcrypt, CSRF on every mutating request, login rate limit, HttpOnly +
  SameSite cookies, upload validation by real mime, no raw HTML from Markdown without sanitising.
  Run the `security-review` skill on your diff before opening the PR.
- The editor must let a non-technical person publish an SEO-strong post in 5 steps; write
  `docs/admin-guide.md` from that perspective. Vendor the Markdown editor JS locally (no CDN).
- SEO score rules are in §5.2; implement them in one class reused by `bin/seo-audit.php`.
- Slug change on a published item MUST insert a 301 and clear the cache; add a test.
- Do not restyle public templates (S3 does that). Admin CSS may be simple but must be usable on a phone.
- Load `fable-cost-guardrail` before spawning subagents; Sonnet at most.
- Minor issues → `KNOWN-ISSUES.md`. Stop only per §4.4.

Exit: CI green; `bin/verify.php` still passes; `tests/admin.test.php` covers login fail/success/
lockout, CSRF rejection, create→publish→slug-change→301, SEO score fixture, 3000px upload → 3 WebP
sizes; PR merged; §9 entry.

## After this phase — hand off to the next (fresh session) — MODEL SWITCH
Only when the four gates in plan §4.9 pass: call claude-code-remote `create_session` with
`model` = Sonnet, inherit environment and permission mode (never `plan`),
`prompt` = `Read prompts/sonnet-3-design.md in this repo and execute it.` Then end with a phase report.
Fallback without `create_session`: STOP and report that S3 must be started in a Sonnet window.
Never Fable. Never hand off with a red or unmerged PR.
