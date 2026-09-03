# Database

- `schema.sql` — the complete object model (plan.md §2). Idempotent; safe to replay.
  It is what a fresh install gets.
- `migrations/NNN_name.sql` — ordered, one-way changes for databases that already exist.

`bin/migrate.php` applies `schema.sql` first, then every migration in `migrations/` that is
not yet recorded in `schema_migrations`. Both are recorded with a checksum, so a re-run is a
no-op unless the file changed.

**Adding a column or table (O-phases only, plan §4.7):** edit `schema.sql` *and* add a
numbered migration with the same change, so fresh installs and live databases stay identical.
