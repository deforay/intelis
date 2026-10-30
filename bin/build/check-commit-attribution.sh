#!/usr/bin/env bash
#
# check-commit-attribution — fail when a commit names an AI tool or vendor.
#
# No commit in this repository credits an AI tool or vendor: not in its message,
# and not in its author or committer. Until 2026-10-30 the only check lived in
# one developer's global git hooks, so six commits made on another machine
# reached GitHub with an attribution trailer and had to be rewritten. This
# script is the shared check. It runs from:
#
#   bin/hooks/commit-msg   before a commit is made
#   bin/hooks/pre-push     before commits leave the machine
#   composer qc            with the other checks
#   the Verify workflow    on every push to master and every pull request, so a
#                          commit from a clone without the hooks still cannot
#                          reach stable
#
# The patterns are stored base64-encoded, so this file names no vendor itself.
# To read them:  echo '<value>' | base64 --decode
#
# There are two tiers, because two of the product names are also people's names.
# A 2019 commit here credits a person of that name, and a pattern on the bare
# word would block every push whose range contains it.
#   hard  names nobody is called. Matched anywhere in a message or identity.
#   ctx   ambiguous names. Matched only in an attribution shape: a co-author
#         trailer, a "generated with" footer, a vendor URL or an email address.
#
# Usage:
#   check-commit-attribution.sh --message-file FILE   check one message (commit-msg)
#   check-commit-attribution.sh RANGE...              check commits, e.g. origin/master..HEAD
#   check-commit-attribution.sh                       check origin/master..HEAD if it exists
set -uo pipefail

hard="$(printf '%s' 'YW50aHJvcGljfGNoYXRncHR8Y29waWxvdHxvcGVuYWl8Y29kZWl1bXxncHQtWzAtOV0=' | base64 --decode)"
ctx="$(printf '%s' 'XltbOnNwYWNlOl1dKmNvLWF1dGhvcmVkLWJ5Oi4qKGNsYXVkZXxnZW1pbmkpfGdlbmVyYXRlZCB3aXRofGNsYXVkZVwuKGNvbXxhaSl8KGNsYXVkZXxnZW1pbmkpW15bOnNwYWNlOl1dKkA=' | base64 --decode)"
if [ -z "$hard" ] || [ -z "$ctx" ]; then
    echo "check-commit-attribution: could not decode the patterns (is base64 installed?)." >&2
    exit 1
fi
pattern="$hard|$ctx"

if [ "${1:-}" = "--message-file" ]; then
    file="${2:?--message-file needs a file}"
    # Lines starting with # are the commit template's comments. git drops them.
    hits="$(grep -niE "$pattern" "$file" | grep -vE '^[0-9]+:[[:space:]]*#' || true)"
    if [ -n "$hits" ]; then
        printf '\n\033[31mcommit blocked\033[0m: the message names an AI tool or vendor:\n%s\n\n' "$hits" >&2
        printf 'Remove that line from the message, then commit again.\n\n' >&2
        exit 1
    fi
    exit 0
fi

ranges=("$@")
if [ "${#ranges[@]}" -eq 0 ]; then
    git rev-parse --verify --quiet refs/remotes/origin/master >/dev/null || exit 0
    ranges=("origin/master..HEAD")
fi

format='  %h  %an <%ae>  %s'
bad=""
for range in "${ranges[@]}"; do
    # A range like "<sha> --not --remotes" arrives as one word from the hooks.
    read -r -a revs <<<"$range"
    hits="$(git log --format="$format  [message]" --extended-regexp --regexp-ignore-case \
        --grep="$pattern" "${revs[@]}" 2>/dev/null)"
    [ -n "$hits" ] && bad="$bad"$'\n'"$hits"
    for who in author committer; do
        hits="$(git log --format="$format  [$who]" --extended-regexp --regexp-ignore-case \
            "--$who=$hard" "${revs[@]}" 2>/dev/null)"
        [ -n "$hits" ] && bad="$bad"$'\n'"$hits"
    done
done

if [ -n "$bad" ]; then
    printf '\n\033[31mThese commits name an AI tool or vendor:\033[0m%s\n\n' "$bad" >&2
    printf 'Rewrite them without it, for example with git rebase and "reword", then push again.\n' >&2
    printf 'For the latest commit only: git commit --amend\n\n' >&2
    exit 1
fi
exit 0
