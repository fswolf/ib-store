#!/usr/bin/env bash
# Deploy store.imbored.me from this folder to the NUC: ./deploy.sh   (run from mewnix)
# Same shape as luna's: one SSH connection for the whole run, web-readable modes (dirs 755, files 644),
# the server's cache/ is left alone. Two store things on top: product images that admin uploaded on the
# server are never deleted by the sync, and cache/ plus assets/images/items/ are made writable by Apache.
# The db is not touched, run ib_store_update.sql by hand when there is one.
set -euo pipefail
NUC="ryan@192.168.0.82"
DEST="/var/www/store.imbored.me"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# one connection, reused by every ssh and rsync below
SSH="ssh -o ControlMaster=auto -o ControlPath=~/.ssh/deploy-%r@%h -o ControlPersist=120"
$SSH "$NUC" true

# first run only: make the target ours so rsync can write it without sudo (asks for the sudo password once, ever)
$SSH -t "$NUC" "[ -w $DEST/public_html ] || sudo sh -c 'mkdir -p $DEST/public_html && chown -R ryan:www-data $DEST'"

# 'P' protects the uploaded product images on the server from --delete, local ones still go up
rsync -a --info=stats1 --delete --chmod=D755,F644 -e "$SSH" \
    --exclude 'public_html/cache' --exclude 'public_html/error.log' --exclude '.git' \
    --filter 'P public_html/assets/images/items/*' \
    "$HERE/public_html" "$NUC:$DEST/"

# apache writes the class map and rate limit files to cache/ and admin uploads land in assets/images/items/.
# those files belong to www-data, so only what is ours gets its group fixed; the class map is dropped so it
# rebuilds with the new paths (we own the folder, so deleting it is fine even though apache wrote it)
$SSH "$NUC" "rm -f $DEST/public_html/cache/class_map.json \
    && mkdir -p $DEST/public_html/cache/req $DEST/public_html/assets/images/items \
    && find $DEST/public_html/cache $DEST/public_html/assets/images/items -user \$(id -un) -exec chgrp www-data {} + -exec chmod g+w {} +"

$SSH -O exit "$NUC" 2>/dev/null || true
echo "deployed: https://store.imbored.me/"
