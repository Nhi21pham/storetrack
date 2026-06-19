# Backups

Automated backups are handled by [`spatie/laravel-backup`](https://spatie.be/docs/laravel-backup),
driven by the existing `scheduler` container. **No manual step is required for backups to run** —
once the stack is up, the scheduler takes care of it.

## What gets backed up

- **Database** — full MySQL dump of the app database (non-locking, `--single-transaction`).
- **Files** — everything under `backend/storage/app` **except**:
  - `storage/app/temp` — regenerable Excel/CSV exports (transient).
  - `storage/app/backup-temp` — spatie's own scratch directory.

Application code, `vendor/`, and `node_modules/` are intentionally **not** backed up — they live in
git / are reinstallable.

## Where backups live

Archives are written to the **`backup_data`** Docker named volume, mounted at
`/var/www/html/storage/backups` inside the `app`, `queue`, and `scheduler` containers.
Each archive is a zip named `storage/backups/<APP_NAME>/<timestamp>.zip`.

> The volume is separate from both the code tree and the `db_data` volume, so a bad migration,
> a `git clean`, or wiping the app container does not touch the backups. It does **not** yet
> survive losing the host — see "Going off-host" below.

## Schedule & retention

| When | What |
|------|------|
| Daily 02:00 | Full backup (database + files) |
| Hourly at :30 | Database-only backup (fills the gaps between daily fulls) |
| Daily 03:00 | `backup:clean` — applies the retention policy |
| Daily 03:30 | `backup:monitor` — flags if newest backup is too old / storage too large |

Retention (`config/backup.php`): keep **all** backups for **4 days**, then thin to daily for 16
days, weekly for 8 weeks, monthly for 4 months, yearly for 2 years. Hard cap: oldest backups are
deleted once total size exceeds **5000 MB**.

## Inspecting / retrieving backups

```bash
# List archives
docker compose exec scheduler ls -lh storage/backups/"$APP_NAME"

# Copy them out of the volume onto the host
docker compose cp scheduler:/var/www/html/storage/backups ./backups-restore
```

## Restoring (the part that matters)

A backup is only real once you've restored from it. There is no auto-restore — do it by hand:

```bash
# 1. Pull the archive out of the volume and unzip the one you want
docker compose cp scheduler:/var/www/html/storage/backups ./backups-restore
unzip ./backups-restore/"$APP_NAME"/<timestamp>.zip -d ./restore-tmp

# 2. Restore the DATABASE (dump is at db-dumps/mysql-<database>.sql inside the archive)
docker compose exec -T db sh -c \
  'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  < ./restore-tmp/db-dumps/mysql-<database>.sql

# 3. Restore FILES (stored under their full path inside the archive)
docker compose cp \
  ./restore-tmp/var/www/html/storage/app/. \
  app:/var/www/html/storage/app/

# 4. Clear caches
docker compose exec app php artisan optimize:clear
```

**Test your backups** into a throwaway database periodically — an untested backup is a guess. This does *not* touch your real `storetrack` DB:

```bash
ZIP=$(docker compose exec -T app sh -c 'ls -t storage/backups/Storetrack/*.zip | head -1' | tr -d '\r')
docker compose exec -T db sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS restore_check; CREATE DATABASE restore_check"'
docker compose exec -T app sh -c "unzip -p '$ZIP' db-dumps/mysql-storetrack.sql" \
  | docker compose exec -T db sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" restore_check'
# look around in restore_check, then drop it:
docker compose exec -T db sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE restore_check"'
```

## Manual backup on demand

```bash
docker compose exec app php artisan backup:run            # database + files
docker compose exec app php artisan backup:run --only-db  # database only
```

## Going off-host (next step)

Local backups protect against accidental deletion and bad migrations, **not** host loss. To get
real disaster recovery:

1. Configure an S3-compatible disk (the `s3` disk already stubbed in `config/filesystems.php`, or
   a Backblaze B2 / DigitalOcean Spaces disk) via env vars.
2. Add that disk name to `destination.disks` **and** `monitor_backups.disks` in `config/backup.php`
   (keep `backups` too — backups then go to both local and off-host).
3. Set `BACKUP_ARCHIVE_PASSWORD` in `.env` to encrypt archives before they leave the host
   (remember: encrypted archives require that password to restore).
4. Optionally set `BACKUP_NOTIFICATION_EMAIL` and configure mail so failures alert someone.
