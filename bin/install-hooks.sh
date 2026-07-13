#!/usr/bin/env bash
#
# Install repo git hooks by symlinking .git/hooks/<name> -> bin/hooks/<name>.
# Run once per clone:  composer install-hooks   (or: bash bin/install-hooks.sh)
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
set -u

root="$(git rev-parse --show-toplevel 2>/dev/null)" || {
    echo "ℹ install-hooks: not a git checkout — skipping."
    exit 0
}
cd "$root" || exit 0
hooks_dir="$(git rev-parse --git-common-dir 2>/dev/null)/hooks"
mkdir -p "$hooks_dir" 2>/dev/null || {
    echo "ℹ install-hooks: can't create $hooks_dir — skipping (non-fatal)." >&2
    exit 0
}

for src in bin/hooks/*; do
    [ -e "$src" ] || continue
    name="$(basename "$src")"
    chmod +x "$src" 2>/dev/null || true
    if ln -sf "$root/bin/hooks/$name" "$hooks_dir/$name" 2>/dev/null; then
        echo "✓ installed $name -> $hooks_dir/$name"
    else
        echo "ℹ install-hooks: couldn't link $name — skipping (non-fatal)." >&2
    fi
done
exit 0
