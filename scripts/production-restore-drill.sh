#!/usr/bin/env bash
set -euo pipefail

# Restore only into an explicitly named scratch database and scratch media directory.
BACKUP_DIR=${1:?Usage: production-restore-drill.sh BACKUP_DIR SCRATCH_DATABASE SCRATCH_MEDIA_DIR}
SCRATCH_DATABASE=${2:?Supply a disposable scratch database name}
SCRATCH_MEDIA=${3:?Supply a disposable scratch media directory}
MYSQL_DEFAULTS_FILE=${MYSQL_DEFAULTS_FILE:-/etc/eventbite/mysql-restore.cnf}
GPG_PRIVATE_KEY_FILE=${BACKUP_GPG_PRIVATE_KEY_FILE:-}

[[ "$SCRATCH_DATABASE" =~ ^eventbite_restore_[a-zA-Z0-9_]+$ ]] || { echo 'Scratch DB must begin eventbite_restore_' >&2; exit 2; }
[[ "$SCRATCH_MEDIA" == /tmp/eventbite-restore-* ]] || { echo 'Scratch media directory must be under /tmp/eventbite-restore-*' >&2; exit 2; }
test -r "$MYSQL_DEFAULTS_FILE"
mkdir -m 700 -p "$SCRATCH_MEDIA"
DB_EXISTS=$(mysql --defaults-extra-file="$MYSQL_DEFAULTS_FILE" --batch --skip-column-names -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$SCRATCH_DATABASE'")
[[ -z "$DB_EXISTS" ]] || { echo 'Scratch database already exists; refusing to overwrite it.' >&2; exit 2; }
mysql --defaults-extra-file="$MYSQL_DEFAULTS_FILE" -e "CREATE DATABASE \`$SCRATCH_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
cd "$BACKUP_DIR"
sha256sum -c SHA256SUMS

if [[ -f database.sql.gz.gpg ]]; then
  test -n "$GPG_PRIVATE_KEY_FILE"
  gpg --batch --import "$GPG_PRIVATE_KEY_FILE"
  gpg --batch --decrypt database.sql.gz.gpg | gunzip -c | mysql --defaults-extra-file="$MYSQL_DEFAULTS_FILE" "$SCRATCH_DATABASE"
  gpg --batch --decrypt private-media.tar.gz.gpg | tar -xz -C "$SCRATCH_MEDIA"
else
  gunzip -c database.sql.gz | mysql --defaults-extra-file="$MYSQL_DEFAULTS_FILE" "$SCRATCH_DATABASE"
  tar -xzf private-media.tar.gz -C "$SCRATCH_MEDIA"
fi

mysql --defaults-extra-file="$MYSQL_DEFAULTS_FILE" --batch --skip-column-names "$SCRATCH_DATABASE" \
  -e "SELECT CONCAT('events=', COUNT(*)) FROM events; SELECT CONCAT('orders=', COUNT(*)) FROM orders; SELECT CONCAT('audit=', COUNT(*)) FROM security_audit_logs;"
test -d "$SCRATCH_MEDIA/private"
if find "$SCRATCH_MEDIA/private" -type f -print -quit | grep -q .; then :; else echo 'Restored media archive contains no files.' >&2; exit 3; fi
echo "Restore drill data loaded into $SCRATCH_DATABASE and $SCRATCH_MEDIA; compare counts with the backup manifest and verify representative media in a staging app before recording RPO/RTO."
