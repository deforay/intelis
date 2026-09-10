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

# A hook there defers to us only if it resolves the repo's OWN hooks dir, which
# means --git-common-dir and nothing else: `git rev-parse --git-path hooks` honors
# core.hooksPath and resolves straight back to the dispatcher's own directory, so
# a hook using it never reaches bin/hooks. Same reason this script targets
# --git-common-dir when installing, noted at the top.
defers() { grep -q 'git-common-dir' "$1" 2>/dev/null; }

same_file() { [ "$(cd "$(dirname "$1")" 2>/dev/null && pwd)/$(basename "$1")" \
              = "$(cd "$(dirname "$2")" 2>/dev/null && pwd)/$(basename "$2")" ]; }

# Our own shim, built once. A shim already in place is compared against this
# BYTE FOR BYTE rather than merely recognised: a shim contains "git-common-dir",
# so treating it as "defers, nothing to do" would freeze whatever version got
# installed first. That is how a fix to the shim would reach nobody who had
# already run --delegate -- which is precisely who is relying on it.
ours="$(mktemp 2>/dev/null)" || {
    echo "ℹ install-hooks: no writable temp dir — skipping the hooksPath check (non-fatal)." >&2
    exit 0
}
trap 'rm -f "$ours"' EXIT
write_shim() { cat > "$1" <<'SHIM'
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

# Hooks handed a payload on stdin can only read it once, so capture it and replay
# it to each. Replay from a FILE, not a pipe: a hook that exits without reading
# (common, and legitimate) would give the writer SIGPIPE, and under pipefail the
# shim would exit 141 and reject the push although the hook itself succeeded.
tmp=""
case "$name" in
    pre-push|pre-receive|post-receive|update|proc-receive)
        # Fail closed. Without the payload the second hook reads EOF, sees no
        # refs, and passes every check it was meant to run -- a silent bypass of
        # the guards, reported as success. Refusing is the safe direction.
        tmp="$(mktemp 2>/dev/null)" || {
            echo "✋ $name blocked — no writable temp dir to hold the ref list." >&2
            echo "  Set TMPDIR to somewhere writable and retry." >&2
            exit 1
        }
        trap 'rm -f "$tmp"' EXIT
        cat > "$tmp"
        ;;
esac

run() {
    h="$1"; shift
    [ -x "$h" ] || return 0
    # Never recurse into this shim if the repo hooks dir happens to be this one.
    [ "$(cd "$(dirname "$h")" && pwd)/$(basename "$h")" = "$here/$name" ] && return 0
    if [ -n "$tmp" ]; then "$h" "$@" < "$tmp"; else "$h" "$@"; fi
}

run "$mine" "$@" || exit $?
run "$repo" "$@" || exit $?
exit 0
SHIM
}
write_shim "$ours"

is_our_shim() { grep -q 'Installed by bin/install-hooks.sh --delegate' "$1" 2>/dev/null; }

shadowed=()
for name in "${names[@]}"; do
    target="$hp/$name"
    if same_file "$target" "$hooks_dir/$name"; then
        continue                            # hooksPath IS the repo hooks dir
    elif [ ! -e "$target" ]; then
        shadowed+=("$name|absent")          # git finds nothing, so nothing runs
    elif is_our_shim "$target"; then
        if cmp -s "$ours" "$target" && [ -x "$target" ]; then
            continue                        # current, and able to run
        fi
        shadowed+=("$name|stale")
    elif [ ! -x "$target" ]; then
        # git skips a hook without the executable bit, so whatever is in it is
        # academic -- including a dispatcher that would otherwise defer.
        if defers "$target"; then
            shadowed+=("$name|notexec")
        else
            shadowed+=("$name|shadowed")
        fi
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
            notexec)  printf '    %-12s %s would defer, but is not executable — git skips it\n' "$n" "$hp/$n" >&2 ;;
            stale)    printf '    %-12s %s is an out-of-date shim — refresh it\n' "$n" "$hp/$n" >&2 ;;
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
mkdir -p "$hp" 2>/dev/null || {
    echo "ℹ install-hooks: can't create $hp — skipping delegation (non-fatal)." >&2
    exit 0
}

for entry in "${shadowed[@]}"; do
    name="${entry%%|*}"
    target="$hp/$name"

    # It already hands off; it just could not run. Nothing to replace.
    if [ "${entry#*|}" = "notexec" ]; then
        chmod +x "$target" 2>/dev/null \
            && echo "✓ made $target executable — it already defers" \
            || echo "ℹ install-hooks: couldn't chmod +x $target (non-fatal)." >&2
        continue
    fi

    if [ -e "$target" ] && ! is_our_shim "$target"; then
        # Never write over an existing backup: that is somebody's hook, and the
        # second run would be the one that destroys it.
        if [ -e "$target.local" ]; then
            echo "ℹ install-hooks: $target.local already exists — leaving $name alone." >&2
            echo "  Merge or remove it, then re-run --delegate." >&2
            continue
        fi
        if ! mv "$target" "$target.local" 2>/dev/null; then
            echo "ℹ install-hooks: couldn't preserve $target — skipping (non-fatal)." >&2
            continue
        fi
        chmod +x "$target.local" 2>/dev/null || true
        echo "✓ kept your existing $name as $name.local"
    fi

    if cp "$ours" "$target" 2>/dev/null; then
        chmod +x "$target" 2>/dev/null || true
        if [ "${entry#*|}" = "stale" ]; then
            echo "✓ refreshed the shim for $name at $target"
        else
            echo "✓ $name at $target now defers to this repo"
        fi
    else
        echo "ℹ install-hooks: couldn't write $target (non-fatal)." >&2
    fi
done
exit 0
