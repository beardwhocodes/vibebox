# Prompt — point Litestream at your Cloudflare R2 bucket

Closes the backup loop: every write to SQLite continuously streams to R2.

```
Configure Litestream on this box to replicate <DB_PATH> to my Cloudflare R2 bucket.

Inputs (I'll paste these in, do NOT echo them back in plaintext):
- R2_BUCKET=<bucket-name>
- R2_ACCOUNT_ID=<account-id>
- R2_ACCESS_KEY_ID=<key>
- R2_SECRET_ACCESS_KEY=<secret>

Steps:
1. Make sure Litestream is installed: `which litestream`. If not, stop and tell me.
2. Write /etc/litestream.yml with the dbs list. Use this skeleton, filling in the values:
     dbs:
       - path: <DB_PATH>
         replicas:
           - type: s3
             bucket: <R2_BUCKET>
             path: vibebox/<HOSTNAME>/<DBNAME>
             endpoint: https://<R2_ACCOUNT_ID>.r2.cloudflarestorage.com
             force-path-style: true
             access-key-id: <R2_ACCESS_KEY_ID>
             secret-access-key: <R2_SECRET_ACCESS_KEY>
   Set the file mode to 0600. Confirm it's not world-readable.
3. Validate: `litestream replicate -config /etc/litestream.yml -exec /bin/true` (dry-run; should
   exit 0).
4. Enable + start the service: `systemctl enable --now litestream`.
5. Verify within 60 seconds:
   - `journalctl -u litestream --since "1 min ago" | tail -n 20` — no errors, sync logs appearing.
   - `litestream replicas <DB_PATH>` lists the R2 replica.
6. Smoke-test the restore path WITHOUT touching the live DB:
   - `litestream restore -o /tmp/restore-test.db -if-replica-exists <DB_PATH>`
   - `sqlite3 /tmp/restore-test.db 'PRAGMA integrity_check;'` returns "ok"
   - delete the test file.

If anything fails, stop and tell me the exact error. Never `rm` the live <DB_PATH>.
```
