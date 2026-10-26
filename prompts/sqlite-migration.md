# Prompt — SQLite schema migration (with backup first)

For any change that alters tables or rewrites data. Don't skip the backup.

```
Migrate the SQLite schema for <PROJECT_DIR>/<DB>. Strict order — do NOT skip any step:

1. BACKUP FIRST. Make a timestamped snapshot:
     cp <DB> <DB>.bak.$(date +%Y%m%d-%H%M%S)
   Verify the copy exists and is non-zero size before continuing.

2. Stop writers if any are running (e.g. `systemctl stop <service>` for any cron/daemon
   touching this DB). Confirm with me before stopping anything besides php-fpm.

3. Inspect the current schema:
     sqlite3 <DB> '.schema'
     sqlite3 <DB> 'PRAGMA integrity_check;'
   Show me both outputs before changing anything.

4. The migration:
   <DESCRIBE THE SCHEMA CHANGE — new column / new table / new index / data transform>

5. Apply it in a single transaction. For destructive changes (drop column, rename), use the
   safe pattern: BEGIN; CREATE new_table; INSERT INTO new_table SELECT ... FROM old; DROP old;
   ALTER RENAME; COMMIT;

6. Verify:
   - `PRAGMA integrity_check;` returns "ok"
   - row counts match expectations
   - app loads (`curl localhost`) and the changed feature works
   - Litestream is still happy if it was running (check journalctl -u litestream --since "5 min ago")

7. Restart writers you stopped.

If anything fails, restore from the backup file and tell me what happened.
```
