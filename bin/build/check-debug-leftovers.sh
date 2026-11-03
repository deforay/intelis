#!/usr/bin/env bash
#
# check-debug-leftovers — fail when a commit adds a debugging stop or dump.
#
# On 2026-10-30 #465 left `die($pQuery);` in the VL patient search. The page
# returned the SQL text instead of the count, and every lab that updated had a
# VL patient search that always said "No Patient Found" until #466.
#
# This script reads only the lines the commits ADD to PHP files under app/ and
# public/, so the old calls already in the code do not block anything. Comment
# lines are skipped. It flags:
#
#   die; die(...)        a stop left in. For a stop that is meant to stay,
#                        use exit instead.
#   var_dump(...)        a dump left in
#   print_r(...)         a dump left in. print_r($x, true) returns a string
#                        and passes.
#
# It runs from .githooks/pre-ship (git ship, git land), composer qc and the
# Verify workflow in CI, which also covers direct pushes to master.
#
# Usage:
#   check-debug-leftovers.sh BASE HEAD   check the lines added in BASE..HEAD (pre-ship)
#   check-debug-leftovers.sh             check everything not yet on origin/master:
#                                        commits, uncommitted changes and new
#                                        untracked files (composer qc, which often
#                                        runs before the commit)
set -uo pipefail

added_lines() {
    if [ $# -eq 2 ]; then
        git diff -U0 --no-color --diff-filter=AMR "$1" "$2" -- 'app/*.php' 'public/*.php'
        return
    fi
    git rev-parse --verify --quiet refs/remotes/origin/master >/dev/null || return 0
    git diff -U0 --no-color --diff-filter=AMR "$(git merge-base origin/master HEAD)" -- 'app/*.php' 'public/*.php'
    git ls-files --others --exclude-standard -- 'app/*.php' 'public/*.php' | while IFS= read -r f; do
        git diff -U0 --no-color --no-index /dev/null "$f"
    done
}

if [ $# -ne 0 ] && [ $# -ne 2 ]; then
    echo "usage: check-debug-leftovers.sh [BASE HEAD]" >&2
    exit 2
fi

hits="$(added_lines "$@" | awk '
    /^\+\+\+ / { file = substr($0, 7); next }
    /^@@ / {
        split($3, a, ",")
        line = substr(a[1], 2) - 1
        next
    }
    /^\+/ {
        line++
        code = substr($0, 2)
        if (code ~ /^[[:space:]]*(\/\/|#|\*|\/\*)/) next
        if (code ~ /(^|[^A-Za-z0-9_>:$])die[[:space:]]*[(;]/ ||
            code ~ /(^|[^A-Za-z0-9_>:$])var_dump[[:space:]]*\(/ ||
            (code ~ /(^|[^A-Za-z0-9_>:$])print_r[[:space:]]*\(/ && code !~ /,[[:space:]]*true[[:space:]]*\)/)) {
            sub(/^[[:space:]]+/, "", code)
            printf "  %s:%d  %s\n", file, line, code
        }
    }
')"

if [ -n "$hits" ]; then
    printf '\n\033[31mThese added lines look like debugging left in:\033[0m\n%s\n\n' "$hits" >&2
    printf 'Remove them, then commit again. If a stop is meant to stay, use exit instead of die.\n\n' >&2
    exit 1
fi
exit 0
