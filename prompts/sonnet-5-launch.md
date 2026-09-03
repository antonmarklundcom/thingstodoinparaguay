# Phase S5 — Staging deploy, cutover runbook, post-launch SEO. Paste into a fresh SONNET session, ONLY after S4 is merged. FINAL PHASE.

Read `plan.md` FIRST, in full — plus §9 build log and `KNOWN-ISSUES.md`. Execute plan §6.3 under the
autonomy protocol §4. Build nothing outside the plan.

HARD LIMITS (plan §4.7). Deployment config and docs only; app changes only to fix Hostinger-specific
breakage found by verify against staging.

Phase rules:
- Branch `phase/s5-launch` off latest `main`. S4 unmerged ⇒ finish it first. Re-runnable.
- Load `nextjs-deploy-hostinger` for hPanel Git deploy, document root, PHP version and LiteSpeed
  cache specifics; adapt to PHP (no Node build). Load `fable-cost-guardrail` before any subagent.
- Never touch the live WordPress installation or DNS. Staging subdomain only. The cutover itself is
  Anton's manual step from the runbook.
- If Hostinger credentials are absent: produce `deploy/` scripts, `docs/cutover-runbook.md`,
  `docs/staging-checklist.md`, log the reason in §9, and treat the phase as complete.
- `bin/verify.php --base=<staging-url>` must pass before you write the runbook's "go" step.
- Minor issues → `KNOWN-ISSUES.md`. Stop only per §4.4.

Exit: staging green (or runbook + scripts + logged reason); runbook has numbered steps incl. rollback,
Search Console sitemap submission and a 20-URL post-cutover spot check; PR merged; §9 entry.

## After this phase — STOP. Do not spawn another session.
Post the closing report: staging URL, admin URL, what Anton must do manually (numbered), the
`KNOWN-ISSUES.md` summary, the §8 open business questions, and a recommendation to create a
project-specific skill (`thingstodoinparaguay-dev`) capturing schema, routes and guardrails.
