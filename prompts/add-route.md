# Prompt — add a route to the monolith

```
In <PROJECT_DIR>/index.php, add a new route `/<ROUTE>` that does the following:

<DESCRIBE THE FEATURE — inputs, behavior, response shape>

Rules:
- Match the existing switch-router style in index.php — don't refactor the router or split files.
- For any SQL: use prepared statements (?-placeholders) for anything from $_GET / $_POST.
- For any HTML output: htmlspecialchars() everything from user input.
- For any DB schema change: add the `CREATE TABLE IF NOT EXISTS` / `CREATE INDEX IF NOT EXISTS`
  at the top of index.php next to the existing schema setup so the table exists for fresh boxes too.
- For any new column on an existing table: add it idempotently with `ALTER TABLE ... ADD COLUMN`
  wrapped in a try/catch so re-running is safe.

When done:
1. Show me the diff (only the parts you changed).
2. Run a `curl` against the new route with a representative input and show me the response.
3. If you added a table or index, run `sqlite3 <DB> '.schema'` and show me the new schema.
```
