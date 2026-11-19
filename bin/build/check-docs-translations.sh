#!/usr/bin/env bash
#
# check-docs-translations — every user and server page in docs/ has a French
# twin with the same shape.
#
# Usage: bash bin/build/check-docs-translations.sh
#
# The site falls back to English when a page has no .fr.md, so a missing
# translation never breaks the build. It only leaves French readers on an
# English page with a French sidebar, which nobody notices until a lab does.
# The same goes for a section added to the English page alone. So this fails
# when a page in the translated folders has no French twin, or when the twin
# has a different number of headings, code blocks, tabs or collapsed boxes.
# Those counts do not depend on the language, and a mismatch is almost always
# a step, a tab or a box that was added or removed on one side only.
#
# The Reference pages at the top of docs/ are written for developers and stay
# in English, so only these folders are checked.
set -euo pipefail

cd "$(dirname "$0")/../.."

FOLDERS=(docs/user-guides docs/guides docs/job-aids)

# Counts the lines that give a page its shape: headings, fence lines, content
# tabs and admonitions (open or collapsed). Fenced code is skipped for the
# heading count, because a `#` comment in a shell block is not a heading.
shape() {
    awk '
        /^[[:space:]]*```/ { fences++; in_code = !in_code; next }
        in_code { next }
        /^#{1,6} /                         { headings++ }
        /^[[:space:]]*=== "/               { tabs++ }
        /^[[:space:]]*(!!!|\?\?\?\+?) /    { boxes++ }
        END { printf "headings=%d code-fences=%d tabs=%d boxes=%d", headings, fences, tabs, boxes }
    ' "$1"
}

failures=0

for folder in "${FOLDERS[@]}"; do
    while IFS= read -r en; do
        fr="${en%.md}.fr.md"
        if [ ! -f "$fr" ]; then
            echo "Missing French page: $fr (for $en)"
            failures=$((failures + 1))
            continue
        fi
        en_shape="$(shape "$en")"
        fr_shape="$(shape "$fr")"
        if [ "$en_shape" != "$fr_shape" ]; then
            echo "Different shape: $en ($en_shape) and $fr ($fr_shape)"
            failures=$((failures + 1))
        fi
    done < <(find "$folder" -maxdepth 1 -name '*.md' ! -name '*.fr.md' | sort)

    # The printable job aids are HTML. Their French twins need only exist.
    while IFS= read -r en; do
        fr="${en%.html}.fr.html"
        if [ ! -f "$fr" ]; then
            echo "Missing French page: $fr (for $en)"
            failures=$((failures + 1))
        fi
    done < <(find "$folder" -maxdepth 1 -name '*.html' ! -name '*.fr.html' | sort)
done

if [ "$failures" -gt 0 ]; then
    echo
    echo "$failures page(s) out of step with French. Update the .fr.md beside each English page."
    exit 1
fi
echo "Every page in ${FOLDERS[*]} has a French twin with the same shape."
