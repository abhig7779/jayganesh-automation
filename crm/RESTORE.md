# Restoring a CRM backup, and the monthly restore drill

A backup only counts if it can be put back. Once a month (first Monday), restore last night's backup into a **test copy** and check it. It takes about 20 minutes.

## What a backup contains
One encrypted zip per night, named like `2026-11-05-18-00-03.zip` (the time is UTC, so 23:30 IST shows as 18:00):
- `db-dumps/mysql-<database>.sql.gz`: the whole database;
- `storage/app/private/…`: bills, job photos and other uploads.

Copies are in two places:
1. on the server: `~/crm-app/storage/app/backups/jayganesh-crm/` (last 30 days, plus `predeploy-….zip` database-only copies taken just before each deploy);
2. in Google Drive, folder `CRM Backups`, as `jayganesh-crm-<date>.zip` (newest 30).

You need `BACKUP_ARCHIVE_PASSWORD` from the password manager to open them.

---

## Monthly drill (safe: never touches the live CRM)

**One-time preparation** in mPanel: create a second database and user, e.g. `abcd_crm_drill` (same steps as DEPLOY.md step 1). This is the only extra thing the drill needs.

1. **Log in over SSH** and make a scratch folder:
   ```bash
   mkdir -p ~/restore-drill && cd ~/restore-drill && rm -rf ./*
   ```
2. **Take last night's zip** (or download one from Google Drive and upload it here with mPanel's File Manager):
   ```bash
   cp "$(ls -1t ~/crm-app/storage/app/backups/jayganesh-crm/20*.zip | head -1)" backup.zip
   ls -lh backup.zip
   ```
3. **Unzip it** (asks for `BACKUP_ARCHIVE_PASSWORD`):
   ```bash
   unzip backup.zip
   ls db-dumps storage/app/private | head
   ```
   If `unzip` says "unsupported compression method", use `7z x backup.zip` instead (AES-encrypted zips need 7-Zip on some servers).
4. **Load the database into the drill database** (it asks for the drill user's password):
   ```bash
   gunzip -c db-dumps/*.sql.gz | mysql -u abcd_crm_drill -p abcd_crm_drill
   ```
5. **Check the numbers look right:**
   ```bash
   mysql -u abcd_crm_drill -p abcd_crm_drill -e "
     SELECT COUNT(*) AS customers FROM customers;
     SELECT COUNT(*) AS staff FROM users;
     SELECT MAX(created_at) AS newest_customer FROM customers;"
   ```
   Compare with the live CRM: the counts should match yesterday's, and the newest record should be from yesterday. Once billing is live, also check `SELECT COUNT(*), MAX(number) FROM invoices;`.
6. **Check the files:** `ls -R storage/app/private | head -30` should show bills/photos.
7. **Clean up:**
   ```bash
   cd ~ && rm -rf ~/restore-drill
   mysql -u abcd_crm_drill -p abcd_crm_drill -e "DROP DATABASE abcd_crm_drill; CREATE DATABASE abcd_crm_drill;"
   ```
   (If the second command is refused, empty the database from mPanel's phpMyAdmin instead.)
8. **Write it down:** date, backup file name, counts, and "OK" or what went wrong, in the drill log below.

| Date | Backup file | Customers | Result | By |
|---|---|---|---|---|
| | | | | |

---

## Real restore (the live CRM lost data)

Only do this when you are sure: it **replaces** the live data with the backup's. Everything entered after the backup time is lost, so note it first (e.g. bills written on paper today).

1. Put the CRM in maintenance mode so nobody types into it:
   ```bash
   cd ~/crm-app && php artisan down
   ```
2. Make a safety copy of the current state, even if it is broken:
   ```bash
   php artisan backup:run --only-db --filename="before-restore-$(date +%Y-%m-%d-%H-%M-%S).zip"
   ```
3. Unzip the chosen backup into `~/restore-drill` (drill steps 1 to 3).
4. Load it into the **live** database (the name and user from `~/crm-app/.env`):
   ```bash
   gunzip -c ~/restore-drill/db-dumps/*.sql.gz | mysql -u abcd_crm -p abcd_crm
   ```
5. Put the files back (adds missing files; doesn't delete newer ones):
   ```bash
   cp -an ~/restore-drill/storage/app/private/. ~/crm-app/storage/app/private/
   ```
6. Bring it back and check:
   ```bash
   cd ~/crm-app && php artisan migrate --force && php artisan optimize && php artisan up && php artisan crm:health
   ```
7. Sign in, open a few recent customers and bills, and tell the staff which time the data goes back to.

If the server itself is gone, follow `crm/DEPLOY.md` on the new server first (steps 1 to 8, skipping `crm:setup` and `crm:create-owner`), then do steps 3 to 6 above with a zip from Google Drive.
