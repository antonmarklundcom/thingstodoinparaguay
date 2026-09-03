# Database

- `schema.sql` — the complete object model (plan.md §2). Idempotent; safe to replay.
  It is what a fresh install gets.
- `migrations/NNN_name.sql` — ordered, one-way changes for databases that already exist.

`bin/migrate.php` applies `schema.sql` first, then every migration in `migrations/` that is
not yet recorded in `schema_migrations`. Both are recorded with a checksum, so a re-run is a
no-op unless the file changed.

**Adding a column or table (O-phases only, plan §4.7):** edit `schema.sql` *and* add a
numbered migration with the same change, so fresh installs and live databases stay identical.

`bin/migrate.php` runs each file statement by statement, splitting on `;` after stripping `--`
comments, and skips an `ALTER TABLE … ADD COLUMN` whose column already exists — that is what lets
the same change live in both `schema.sql` (for fresh installs) and a migration (for live ones).
The splitter is deliberately simple: a migration containing a trigger, a `BEGIN … END` block or a
semicolon inside a string literal would need a real parser first.
