#!/usr/bin/env bash
# Refresh the vendored libraries in public_html: ./update-libs.sh   (no composer, same idea as the flight/ folder)
# Pulls the newest tagged release of each one from GitHub (shallow git clone), swaps the folder, writes a VERSION file.
# Run it, read the diff, then deploy. Nothing here touches the server.
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PUB="$HERE/public_html"

# name | github repo | folder inside the repo to keep | where it lands in public_html
# the folder name is the php namespace root, so autoloader.php finds the classes through assets/vendor/
LIBS=(
    "btcpay|btcpayserver/btcpayserver-greenfield-php|src|assets/vendor/BTCPayServer"
)

for lib in "${LIBS[@]}"; do
    IFS='|' read -r name repo subdir dest <<< "$lib"

    # newest tag by version sort, master when the repo has no tags
    tag=$(git ls-remote --tags --refs "https://github.com/$repo" | sed 's|.*refs/tags/||' | sort -V | tail -1)
    ref=${tag:-master}
    current=$(cat "$PUB/$dest/VERSION" 2>/dev/null || echo "none")

    if [ "$current" = "$ref" ]; then
        echo "$name: already at $ref"
        continue
    fi

    echo "$name: $current -> $ref"
    tmp=$(mktemp -d)
    git -c advice.detachedHead=false clone -q --depth 1 --branch "$ref" "https://github.com/$repo" "$tmp/repo"
    src="$tmp/repo/$subdir"
    [ -d "$src" ] || { echo "$name: no $subdir folder in the checkout"; rm -rf "$tmp"; exit 1; }

    rm -rf "$PUB/$dest"
    mkdir -p "$(dirname "$PUB/$dest")"
    cp -r "$src" "$PUB/$dest"
    cp "$(dirname "$src")/LICENSE" "$PUB/$dest/" 2>/dev/null || true
    rm -f "$PUB/$dest/autoload.php"   # ours does the loading, see autoloader.php
    echo "$ref" > "$PUB/$dest/VERSION"
    rm -rf "$tmp"
done

echo "done. php -l check:"
find "$PUB/assets/vendor" -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || echo "all clean"
