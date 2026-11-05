#!/usr/bin/env bash
set -euo pipefail

# MySQL dump + private media archive. Credentials are read from MYSQL_DEFAULTS_FILE;
# encryption key material comes from the host secret store, never this repository.
APP_ROOT=${APP_ROOT:-/var/www/eventbite}
BACKUP_ROOT=${BACKUP_ROOT:-/var/backups/eventbite}
MYSQL_DEFAULTS_FILE=${MYSQL_DEFAULTS_FILE:-/etc/eventbite/mysql-backup.cnf}
RETENTION_DAYS=${RETENTION_DAYS:-30}
GPG_RECIPIENT=${BACKUP_GPG_RECIPIENT:-}
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
DEST="$BACKUP_ROOT/$STAMP"

test -r "$MYSQL_DEFAULTS_FILE"
test -d "$APP_ROOT/shared/storage/app/private"
install -d -m 700 "$DEST"
TMP="$DEST/database.sql.gz.tmp"
trap 'rm -f "$TMP"' EXIT

mysqldump --defaults-extra-file="$MYSQL_DEFAULTS_FILE" --single-transaction --quick --routines --triggers \
  --set-gtid-purged=OFF --no-tablespaces "${MYSQL_DATABASE:?Set MYSQL_DATABASE in the service environment}" \
  | gzip -9 > "$TMP"
gzip -t "$TMP"
mv "$TMP" "$DEST/database.sql.gz"

tar -C "$APP_ROOT/shared/storage/app" -czf "$DEST/private-media.tar.gz" private
tar -tzf "$DEST/private-media.tar.gz" >/dev/null

# Capture pre-backup row counts and timestamp to support restore verification.
APP_ENV=production php "$APP_ROOT/current/scripts/production-backup-manifest.php" > "$DEST/manifest.json"

if [[ -n "$GPG_RECIPIENT" ]]; then
  command -v gpg >/dev/null
  for artifact in database.sql.gz private-media.tar.gz; do
    gpg --batch --yes --trust-model always --recipient "$GPG_RECIPIENT" --encrypt --output "$DEST/$artifact.gpg" "$DEST/$artifact"
    rm -f "$DEST/$artifact"
  done
fi

(cd "$DEST" && sha256sum * > SHA256SUMS)
chmod -R go-rwx "$DEST"
# Copy the completed directory to a separately administered, versioned off-site target here.
# A backup is not complete for recovery until that copy succeeds.
if [[ -n "${BACKUP_OFFSITE_COMMAND:-}" ]]; then
  bash -euo pipefail -c "$BACKUP_OFFSITE_COMMAND '$DEST'"
else
  echo 'WARNING: no BACKUP_OFFSITE_COMMAND configured; backup is local only.' >&2
fi

find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -mtime "+$RETENTION_DAYS" -exec rm -rf -- {} +
echo "Backup completed: $DEST"
