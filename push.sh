#!/usr/bin/env bash
# Push this folder to GitHub: ./push.sh "what changed"   (message optional)
# First run makes the repo (git init, main branch, remote) and creates it on GitHub through gh when gh is
# installed, otherwise it tells you to make the empty repo on github.com first. After that it is add, commit, push.
# Two guards before anything goes up: config.php must not be tracked, and ib_store.sql must hold no account rows.
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="fswolf/ib-store"
cd "$HERE"

# -- first run -- //
if [ ! -d .git ]; then
    git init -b main
fi
if ! git remote get-url origin >/dev/null 2>&1; then
    if command -v gh >/dev/null && gh auth status >/dev/null 2>&1; then
        gh repo create "$REPO" --public --source . --remote origin
    else
        git remote add origin "git@github.com:$REPO.git"
        echo "remote set to git@github.com:$REPO.git, make the empty repo at https://github.com/new if it is not there yet"
    fi
fi

# -- guards -- //
if git ls-files --error-unmatch public_html/config/config.php >/dev/null 2>&1; then
    echo "config.php is tracked, that is your database password. git rm --cached public_html/config/config.php and try again" >&2
    exit 1
fi
if grep -q 'INSERT INTO `accounts`\|INSERT INTO `login_sessions`' ib_store.sql; then
    echo "ib_store.sql has account rows in it, export the schema without them before pushing" >&2
    exit 1
fi

# -- commit and push -- //
git add -A
if git diff --cached --quiet; then
    echo "nothing to commit"
else
    git commit -m "${*:-update $(date +%Y-%m-%d)}"
fi
git push -u origin main
echo "pushed: https://github.com/$REPO"
