#!/usr/bin/env bash
#
# Install repo git hooks by symlinking .git/hooks/<name> -> bin/hooks/<name>.
# Run once per clone:  composer install-hooks   (or: bash bin/install-hooks.sh)
#
#   bash bin/install-hooks.sh              install, then report what will actually run
#   bash bin/install-hooks.sh --delegate   also make a global core.hooksPath defer to us
#
# Best-effort by design: it is wired into composer's post-install-cmd, so it must
# NEVER fail the composer run (a failing script would abort the deploy). Any
# problem -> warn + exit 0. It is also a clean no-op on non-git (tarball) deploys.
# In practice prod's upgrade path runs `composer install --no-scripts`, so this
# does not run there at all — this hardening only covers edge paths (e.g. the
# rollback vendor-rebuild) that do execute scripts.
#
# NOTE: targets `git rev-parse --git-common-dir`/hooks (the literal repo hooks dir),
# NOT `--git-path hooks` -- the latter honors core.hooksPath and would write into a
# globally-configured hooks dir instead of this repo's.
#
# WHY THE REPORT EXISTS: core.hooksPath REPLACES .git/hooks outright. Where it is
# set, every symlink written below is inert and git says nothing. This script used
# to print "installed" and exit 0 into that void, so a machine could go months with
# no conflict-marker guard, no php -l, no PSR12 check, and no sign of it -- which is
# exactly what happened: a style violation reached CI from a clone whose hooks had
# never once run. Installing is not the same as running, so we now check and say so.
set -u

# Silent no-op on non-git (tarball) deploys — prod output should not mention hooks.
root="$(git rev-parse --show-toplevel 2>/dev/null)" || exit 0
cd "$root" || exit 0

delegate=""
[ "${1:-}" = "--delegate" ] && delegate=1

hooks_dir="$(git rev-parse --git-common-dir 2>/dev/null)/hooks"
mkdir -p "$hooks_dir" 2>/dev/null || {
    echo "ℹ install-hooks: can't create $hooks_dir — skipping (non-fatal)." >&2
    exit 0
}

names=()
for src in bin/hooks/*; do
    [ -e "$src" ] || continue
    name="$(basename "$src")"
    names+=("$name")
    chmod +x "$src" 2>/dev/null || true
    if ln -sf "$root/bin/hooks/$name" "$hooks_dir/$name" 2>/dev/null; then
        echo "✓ installed $name -> $hooks_dir/$name"
    else
        echo "ℹ install-hooks: couldn't link $name — skipping (non-fatal)." >&2
    fi
done

# ---------------------------------------------------------------------------
# Does any of that actually run?
# ---------------------------------------------------------------------------
hp="$(git config --get core.hooksPath 2>/dev/null)" || hp=""
[ -z "$hp" ] && exit 0                      # .git/hooks is live; nothing to warn about

# core.hooksPath may be absolute, ~-relative, or relative to the working tree.
case "$hp" in
    "~") hp="$HOME" ;;
    "~/"*) hp="$HOME/${hp#\~/}" ;;
    /*) ;;
    *) hp="$root/$hp" ;;
esac
hp="${hp%/}"

# A hook there defers to us if it looks up the repo's own hooks dir. Both spellings
# git offers for that are accepted; a dispatcher using either will find bin/hooks.
defers() { grep -qE 'git-common-dir|--git-path[[:space:]]+hooks' "$1" 2>/dev/null; }

same_file() { [ "$(cd "$(dirname "$1")" 2>/dev/null && pwd)/$(basename "$1")" \
              = "$(cd "$(dirname "$2")" 2>/dev/null && pwd)/$(basename "$2")" ]; }

shadowed=()
for name in "${names[@]}"; do
    target="$hp/$name"
    if same_file "$target" "$hooks_dir/$name"; then
        continue                            # hooksPath IS the repo hooks dir
    elif [ ! -e "$target" ]; then
        shadowed+=("$name|absent")          # git finds nothing, so nothing runs
    elif defers "$target"; then
        continue                            # a dispatcher that hands off to us
    else
        shadowed+=("$name|shadowed")
    fi
done

[ "${#shadowed[@]}" -eq 0 ] && exit 0

if [ -z "$delegate" ]; then
    printf '\n\033[33m⚠ core.hooksPath is set to %s\033[0m\n' "$hp" >&2
    printf '  git ignores %s entirely while it is, so the hooks installed above DO NOT RUN:\n' "$hooks_dir" >&2
    for entry in "${shadowed[@]}"; do
        n="${entry%%|*}"
        case "${entry#*|}" in
            absent)   printf '    %-12s no %s — git finds no hook of this name at all\n' "$n" "$hp/$n" >&2 ;;
            shadowed) printf '    %-12s %s runs instead, and does not hand off\n' "$n" "$hp/$n" >&2 ;;
        esac
    done
    printf '\n  Make that directory defer to this repo (keeps whatever is already there):\n' >&2
    printf '    \033[36mbash bin/install-hooks.sh --delegate\033[0m\n' >&2
    printf '  Or drop the global setting if you do not need it:\n' >&2
    printf '    \033[36mgit config --global --unset core.hooksPath\033[0m\n\n' >&2
    exit 0
fi

# ---------------------------------------------------------------------------
# --delegate: install a shim that runs the machine's own hook, then the repo's.
# Anything already there is preserved as <name>.local and called first, so a
# personal guard keeps working rather than being replaced by ours.
# ---------------------------------------------------------------------------
for entry in "${shadowed[@]}"; do
    name="${entry%%|*}"
    target="$hp/$name"

    if [ -e "$target" ] && ! grep -q 'install-hooks.sh --delegate' "$target" 2>/dev/null; then
        if ! mv "$target" "$target.local" 2>/dev/null; then
            echo "ℹ install-hooks: couldn't preserve $target — skipping (non-fatal)." >&2
            continue
        fi
        chmod +x "$target.local" 2>/dev/null || true
        echo "✓ kept your existing $name as $name.local"
    fi

    cat > "$target" <<'SHIM' 2>/dev/null || { echo "ℹ install-hooks: couldn't write $target (non-fatal)." >&2; continue; }
#!/usr/bin/env bash
# Installed by bin/install-hooks.sh --delegate.
#
# core.hooksPath replaces .git/hooks wholesale, so a repo's own hooks never run
# unless something here calls them. This runs the machine-local hook of this name
# (kept as <name>.local), then the repo's, and fails the operation if either does.
set -uo pipefail

name="$(basename "$0")"
here="$(cd "$(dirname "$0")" && pwd)"
mine="$here/$name.local"
repo="$(git rev-parse --git-common-dir 2>/dev/null)/hooks/$name"

# Hooks that are handed their payload on stdin can only read it once, so capture
# it here and replay it to each. Distinguish "no payload" from "empty payload":
# a pre-push of nothing is a real case and must not be turned into a hang.
payload=""; piped=""
case "$name" in
    pre-push|pre-receive|post-receive|update|proc-receive) payload="$(cat)"; piped=1 ;;
esac

run() {
    [ -x "$1" ] || return 0
    # Never recurse into this shim if the repo hooks dir happens to be this one.
    [ "$(cd "$(dirname "$1")" && pwd)/$(basename "$1")" = "$here/$name" ] && return 0
    if [ -n "$piped" ]; then printf '%s\n' "$payload" | "$1" "${@:2}"; else "$1" "${@:2}"; fi
}

run "$mine" "$@" || exit $?
run "$repo" "$@" || exit $?
exit 0
SHIM
    chmod +x "$target" 2>/dev/null || true
    echo "✓ $name at $target now defers to this repo"
done
exit 0
