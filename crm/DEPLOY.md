# Putting the CRM live on MilesWeb

This guide takes the CRM from GitHub to **https://crm.jayganeshcaraccessories.com**. Do the first-time steps once, in order. After that, every update is one button in GitHub (see "Routine deploys").

**Already true (checked 5 Nov 2026):** the subdomain `crm.jayganeshcaraccessories.com` exists in mPanel with document root `crm-app/public`. The app lives in `~/crm-app`, and only its `public/` folder is visible on the web.

**Golden rule:** this GitHub repo is **public**. Passwords, the app key and the `.env` file never go into git, chat or screenshots. Type them only into mPanel, the server, or GitHub's **Secrets** page.

---

## First-time setup

### 1. Create the database (mPanel)
1. mPanel → **Databases → MySQL Databases**.
2. Create a database, for example `crm`. mPanel adds your account prefix, so the real name looks like `abcd_crm`. Write the full name down.
3. Create a user, for example `crm`. Real name: `abcd_crm`. Click **Generate** for a long password and save it in your password manager. You will type it once, in step 6.
4. Add the user to the database with **ALL PRIVILEGES**.

### 2. Set PHP 8.4 for the crm subdomain (mPanel)
1. mPanel → **PHP Selector** (or "Select PHP Version") → pick the `crm.jayganeshcaraccessories.com` site → **8.4**.
2. Turn on these extensions if they are listed and off: `pdo_mysql, mbstring, openssl, gd, zip, intl, bcmath, fileinfo, curl`.
3. Set `memory_limit` to **256M** (bills are made into PDFs) and `max_execution_time` to **120**.
4. Some hosts save the PHP version as a line in `crm-app/public/.htaccess` (it starts with `AddHandler` or `<IfModule mime_module>`). Each deploy replaces that file, so after step 7, check the file once over SSH (`head -5 ~/crm-app/public/.htaccess`). If you see such a line at the top that is **not** in the repo's copy, save it like this and every deploy will put it back:
   ```bash
   mkdir -p ~/crm-app/.deploy
   nano ~/crm-app/.deploy/htaccess-prepend    # paste only the host's PHP line(s), save
   ```

### 3. Make an SSH key for GitHub (on your Mac)
Open **Terminal** on the Mac and paste:
```bash
ssh-keygen -t ed25519 -C "github-crm-deploy" -f ~/.ssh/jg_crm_deploy -N ""
cat ~/.ssh/jg_crm_deploy.pub
```
- The line that starts with `ssh-ed25519` is the **public** key. mPanel → **SSH Access → SSH Keys → Import key** → paste it → **Authorize** it.
- The file `~/.ssh/jg_crm_deploy` (no `.pub`) is the **private** key. It goes only into GitHub secrets (next step). Never paste it anywhere else.
- In mPanel's SSH page, note the **host** (usually the server name or `45.199.139.29`), the **port** (often 22 or a custom one like 2222) and your **SSH username**.

Test it from the Mac (put in your own user, host and port):
```bash
ssh -i ~/.ssh/jg_crm_deploy -p PORT USER@HOST "echo connected; which php php84 php8.4; php -v | head -1; which mysqldump"
```
Write down which PHP path prints **8.4**. If plain `php` is already 8.4, the answer is just `php`. On many hosts it is something like `/opt/alt/php84/usr/bin/php` or `/usr/local/bin/php84`.

### 4. Add the GitHub secrets and variables
GitHub → the repo → **Settings → Secrets and variables → Actions**.

**Secrets** tab → **New repository secret** for each:

| Name | Value |
|---|---|
| `CRM_SSH_HOST` | the SSH host from step 3 |
| `CRM_SSH_PORT` | the SSH port (leave out if it is 22) |
| `CRM_SSH_USER` | the SSH username |
| `CRM_SSH_KEY` | the whole private key: on the Mac run `cat ~/.ssh/jg_crm_deploy` and copy every line, including `-----BEGIN…` and `-----END…` |
| `CRM_SSH_KNOWN_HOSTS` *(optional, safer)* | the output of `ssh-keyscan -p PORT HOST` on the Mac |
| `GDRIVE_BACKUP_FOLDER_ID` | see "Backups to Google Drive" below |
| `GDRIVE_SA_JSON` | see "Backups to Google Drive" below |

**Variables** tab → **New repository variable**:

| Name | Value |
|---|---|
| `CRM_PHP_BIN` | the PHP 8.4 path from step 3, e.g. `php` or `/opt/alt/php84/usr/bin/php` |
| `CRM_URL` | `https://crm.jayganeshcaraccessories.com` (this is the default anyway) |
| `CRM_AUTO_DEPLOY` | leave unset for now (see "Routine deploys") |

Then **Settings → Environments → New environment** → name it `production`. Optionally tick **Required reviewers** and add yourself: every deploy then waits for your click.

### 5. First upload of the code
GitHub → **Actions → CRM deploy → Run workflow** (branch `main`) → **Run**.

It runs all tests, builds the app and copies it to `~/crm-app`. On this first run it stops with a yellow note: *".env does not exist yet"*. That is expected.

### 6. First-time commands on the server
Log in from the Mac (same command as step 3 without the quoted part), then paste these one block at a time. Where it says `php`, use your PHP path from step 3 if it is different.

```bash
cd ~/crm-app
cp .env.production.example .env
nano .env
```
In the editor fill in `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (step 1), `MAIL_PASSWORD` (the mailbox you create in mPanel → Email for `crm@jayganeshcaraccessories.com`), `BACKUP_NOTIFY_EMAIL` (your email) and `BACKUP_ARCHIVE_PASSWORD` (a new long password; **save it in your password manager**, old backups can't be opened without it). Save with `Ctrl+O`, `Enter`, `Ctrl+X`.

```bash
chmod 600 .env
php artisan key:generate --force
php artisan migrate --force
php artisan crm:setup
php artisan crm:create-owner
php artisan storage:link
php artisan config:cache
```
`crm:create-owner` asks for your name, mobile and a password. Type them at the prompt; they are never stored anywhere else.

Also copy the `APP_KEY=` line from `.env` into your password manager. If it is lost, everyone is signed out and encrypted values can't be read.

### 7. Run the deploy again
GitHub → **Actions → CRM deploy → Run workflow**. This time it migrates, caches, and finishes with a green **Smoke test** (`/up` answered 200).

### 8. The cron line (makes queues, backups and alerts work)
mPanel → **Cron Jobs** → add one job, **every minute** (`* * * * *`):
```bash
cd ~/crm-app && php artisan schedule:run >> /dev/null 2>&1
```
Use your PHP path from step 3 instead of `php` if it is different, e.g.
`cd ~/crm-app && /opt/alt/php84/usr/bin/php artisan schedule:run >> /dev/null 2>&1`.

This single line runs everything on time:

| When (India time) | What |
|---|---|
| every minute | heartbeat for the Health page; a queue worker that sends WhatsApp/PDF jobs and stops when the queue is empty (at most 50 s) |
| 23:30 daily | delete backups older than 30 days, then make tonight's backup |
| 08:00 daily | email of yesterday's errors (skipped when there were none) |
| 09:00 daily | check the newest backup is less than a day old; emails you if not |

### 9. Turn on SSL
mPanel → **SSL/TLS** (or "Let's Encrypt") → issue a free certificate for `crm.jayganeshcaraccessories.com` → turn on auto-renew. The app itself sends every `http://` visit to `https://` and tells browsers to always use HTTPS.

### 10. Check it works
1. Open **https://crm.jayganeshcaraccessories.com/up**. It should show a plain "Application up" page.
2. Sign in with the owner login from step 6.
3. Open **https://crm.jayganeshcaraccessories.com/health** (owner only). Wait 2 minutes after adding the cron line. Everything should be green except **Last backup**, which turns green after the first 23:30 run. To make one now:
   ```bash
   cd ~/crm-app && php artisan backup:run && php artisan crm:health
   ```
4. In GitHub, **Actions → CRM backup to Google Drive → Run workflow** once to prove the Drive copy works.

---

## Routine deploys

1. Changes are merged into `main` (CI must be green).
2. GitHub → **Actions → CRM deploy → Run workflow** → **Run**.
3. It runs every test again on PHP 8.2 and 8.4 and on MySQL. If any fail, nothing is deployed.
4. It then: shows "The CRM is being updated" to users (they wait under a minute; the page reloads itself) → takes a quick database backup → uploads the new code → runs migrations → rebuilds caches → restarts the queue worker → brings the site back → checks `/up`.

`.env`, `storage/` (uploads, PDFs, logs, backups) and everything you saved in `.deploy/` are never touched by a deploy.

**Automatic deploys (optional, later):** add the variable `CRM_AUTO_DEPLOY` = `true` and every push to `main` that changes `crm/app` deploys by itself. Delete the variable to switch it off.

### Undo a bad deploy
1. **Actions → CRM deploy → Run workflow**, and in **"Branch, tag or commit to deploy"** paste the last good commit (copy it from the repo's commit list). Same steps, older code.
2. Only if the bad release added a database change that must be undone, over SSH:
   ```bash
   cd ~/crm-app && php artisan migrate:rollback --step=1 --force
   ```
   If unsure, restore the backup taken just before the deploy instead (`predeploy-….zip`, see `crm/RESTORE.md`).
3. If the site is stuck on "being updated" (a deploy stopped half-way):
   ```bash
   cd ~/crm-app && php artisan up
   ```
   A deploy that fails during migrations leaves the site in this mode on purpose, so nobody bills against a half-updated database. Read the red step in GitHub Actions first.

---

## Backups to Google Drive

- **On the server**, every night at 23:30 IST, the app zips a database dump plus `storage/app/private` (bills, photos, uploads) into `~/crm-app/storage/app/backups/jayganesh-crm/`, encrypted with `BACKUP_ARCHIVE_PASSWORD`. It keeps 30 days and emails `BACKUP_NOTIFY_EMAIL` if a backup fails or gets old.
- **At 01:00 IST**, the GitHub Action **CRM backup to Google Drive** downloads the newest zip over SSH and uploads it to a Drive folder as `jayganesh-crm-<date>.zip`. It keeps the newest **30** copies in that folder and removes older ones. It fails (GitHub emails you) if the newest server backup is more than 30 hours old.
- The zip is never saved in GitHub (the repo is public).

**Set up the Drive side once.** Pick **one** option:

**Option A: service account (needs Google Workspace).** Service accounts have no storage of their own, so the folder must be in a **Shared drive**.
1. Google Cloud Console → your project → **IAM & Admin → Service accounts → Create**, name `crm-backup`. Open it → **Keys → Add key → JSON**. A `.json` file downloads.
2. **APIs & Services → Library** → enable **Google Drive API**.
3. Google Drive → **Shared drives** → make or open one → create folder `CRM Backups` → **Manage members** → add the service account's email (ends in `iam.gserviceaccount.com`) as **Content manager**.
4. Open the folder. The ID is the last part of the address bar: `drive.google.com/drive/folders/`**`THIS_PART`**.
5. GitHub secrets: `GDRIVE_SA_JSON` = the whole content of the `.json` file; `GDRIVE_BACKUP_FOLDER_ID` = the folder ID. Then delete the `.json` file from your Downloads.

**Option B: the Google login the posting system already uses (works with plain Gmail / My Drive).**
The repo already has the secrets `GBP_CLIENT_ID`, `GBP_CLIENT_SECRET` and `DRIVE_REFRESH_TOKEN`. Leave `GDRIVE_SA_JSON` empty, create a `CRM Backups` folder in that Google account's My Drive, and set only `GDRIVE_BACKUP_FOLDER_ID`. The workflow falls back to that login automatically.

Restore steps and the monthly drill: `crm/RESTORE.md`.

---

## Health and alerts

- **`/health`** (owner only) and **`php artisan crm:health`** check: the database answers; no queued job has waited more than 10 minutes; the newest backup is less than 26 hours old; the app's folders are writable; the scheduler ran in the last 3 minutes. The command prints `OK` or `FAIL` and exits with an error code on failure.
- **Daily error email** at 08:00 IST to `ERROR_DIGEST_EMAIL` (or `BACKUP_NOTIFY_EMAIL`), grouping the last 24 hours of errors from `storage/logs`. Phone numbers are masked. No errors means no email. Switch off with `ERROR_DIGEST_ENABLED=false` in `.env`, then `php artisan config:cache`.

### When something is red
| Red line | Usually means | Fix |
|---|---|---|
| Scheduler | the cron line is missing or uses the wrong PHP | redo step 8; test by running the command by hand over SSH |
| Queue | the cron line stopped, or a job keeps failing | check the cron; `php artisan queue:failed` lists failures, `php artisan queue:retry all` retries |
| Last backup | `mysqldump` not found, or the disk is full | `which mysqldump`; if empty, ask MilesWeb, or set `DB_DUMP_BINARY_PATH` to its folder. Run `php artisan backup:run` and read the error |
| Storage writable | wrong folder permissions | `chmod -R u+rwX ~/crm-app/storage ~/crm-app/bootstrap/cache` |
| Database | wrong `.env` values or MySQL down | check `DB_*` in `.env`, then `php artisan config:cache` |

---

## Security headers (for developers)

`app/Http/Middleware/SecurityHeaders.php` adds to every response: HSTS (1 year), `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, a `Permissions-Policy`, and a Content-Security-Policy:

```
default-src 'self'; script-src 'self' 'nonce-…' 'unsafe-eval'; style-src 'self' 'unsafe-inline';
img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; object-src 'none';
base-uri 'self'; form-action 'self'; frame-ancestors 'none'; manifest-src 'self'; upgrade-insecure-requests
```

**Decision:** we keep the normal Alpine build, so `'unsafe-eval'` stays (Alpine evaluates `x-data`/`@click` with `new Function`, and htmx's `hx-on` does the same). The CSP build of Alpine would remove it, but every inline Alpine expression in every module would then have to move into `Alpine.data()`. For a staff-only app behind a login, with Blade escaping all output, that trade is not worth it now. Inline `<script>` tags **are** blocked: put code in `resources/js`, or add `nonce="{{ Vite::cspNonce() }}"` to the tag. No CDNs: everything is bundled by Vite.

If a new screen breaks because of the policy, set `SECURITY_CSP=report-only` in `.env` (then `php artisan config:cache`), read the browser console, fix, and set it back to `enforce`.
