#!/usr/bin/env bash
# Refresh the vendored libraries in public_html: ./update-libs.sh   (no composer, same idea as the flight/ folder)
# Pulls the newest release of each one, from GitHub (newest tag, shallow git clone) or npm (the latest version's
# tarball, for js that only ships built files there), swaps the folder, writes a VERSION file.
# Run it, read the diff, then deploy. Nothing here touches the server.
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PUB="$HERE/public_html"

# name | github:owner/repo or npm:package | folder inside it to keep | where it lands in public_html
# btcpay: the folder name is the php namespace root, so autoloader.php finds the classes through assets/vendor/
# easymde: the product description editor on the admin product page (its icons are inline svg, no icon font)
LIBS=(
    "btcpay|github:btcpayserver/btcpayserver-greenfield-php|src|assets/vendor/BTCPayServer"
    "easymde|npm:easymde|dist|assets/vendor/easymde"
)

for lib in "${LIBS[@]}"; do
    IFS='|' read -r name source subdir dest <<< "$lib"
    repo=${source#*:}

    # github: newest tag by version sort, master when the repo has no tags. npm: what the registry calls latest
    if [ "${source%%:*}" = "npm" ]; then
        ref=$(curl -fsSL "https://registry.npmjs.org/$repo/latest" | grep -o '"version":"[^"]*"' | head -1 | cut -d'"' -f4)
    else
        tag=$(git ls-remote --tags --refs "https://github.com/$repo" | sed 's|.*refs/tags/||' | sort -V | tail -1)
        ref=${tag:-master}
    fi
    current=$(cat "$PUB/$dest/VERSION" 2>/dev/null || echo "none")

    if [ "$current" = "$ref" ]; then
        echo "$name: already at $ref"
        continue
    fi

    echo "$name: $current -> $ref"
    tmp=$(mktemp -d)
    if [ "${source%%:*}" = "npm" ]; then
        mkdir "$tmp/repo"
        curl -fsSL "https://registry.npmjs.org/$repo/-/$repo-$ref.tgz" | tar xz -C "$tmp/repo" --strip-components=1
    else
        git -c advice.detachedHead=false clone -q --depth 1 --branch "$ref" "https://github.com/$repo" "$tmp/repo"
    fi
    [ -d "$tmp/repo/$subdir" ] || { echo "$name: no $subdir folder in the download"; rm -rf "$tmp"; exit 1; }

    rm -rf "$PUB/$dest"
    mkdir -p "$(dirname "$PUB/$dest")"
    cp -r "$tmp/repo/$subdir" "$PUB/$dest"
    cp "$tmp/repo/LICENSE" "$PUB/$dest/" 2>/dev/null || true
    rm -f "$PUB/$dest/autoload.php"   # ours does the loading, see autoloader.php
    echo "$ref" > "$PUB/$dest/VERSION"
    rm -rf "$tmp"
done

echo "done. php -l check:"
find "$PUB/assets/vendor" -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || echo "all clean"
