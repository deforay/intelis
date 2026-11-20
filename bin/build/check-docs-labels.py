#!/usr/bin/env python3
"""check-docs-labels: every screen label a guide quotes still exists.

Usage: python3 bin/build/check-docs-labels.py

The guides name what to select in bold: **Save**, or a menu path such as
**ADMIN** → **Users & Roles** → **Users**. When the app renames a button or a
menu entry, the guide still quotes the old name, and nothing fails until a
reader cannot find it. This check reads the bold text on every page in the
guide folders, splits menu paths on the arrow, and looks for each label in the
source tree: the app, the scripts, the SQL that seeds the menus, and the
translation files. The French pages pass on the French translations.

A label also passes when it:

- matches a string once its blanks are filled in: a translation with %s or
  %d, such as "%d samples per pool", or a script or PHP string with a
  variable, such as "Type ${CHOSEN} to confirm";
- names a heading, tab or box title on any docs page, as cross-references do;
- is a keyboard key, such as Ctrl+C;
- ends with a full stop, because then it is emphasis, not a label.

Everything else that is not in the app goes in docs-labels-allowlist.txt
beside this script: screens of Windows, WampServer and the Interface Tool,
and labels a page builds from parts. Only stdlib, so it runs before the docs
dependencies are installed.
"""

from __future__ import annotations

import html
import os
import re
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
FOLDERS = ["docs/user-guides", "docs/guides"]
SOURCES = ["app", "bin", "scripts", "sql", "sys", "public", "run-once", "intelis"]
SOURCE_EXT = (".php", ".js", ".sh", ".sql", ".po", ".json", ".html", ".twig", ".py")
ALLOWLIST = os.path.join(os.path.dirname(os.path.abspath(__file__)), "docs-labels-allowlist.txt")

# Menu paths: **A** → **B**, or one bold run holding A → B.
PATH_SEPARATOR = re.compile(r"\s*(?:→|›|&rsaquo;|&gt;)\s*")
# A bold run may wrap onto the next line, but never across a blank line.
BOLD = re.compile(r"\*\*((?:(?!\*\*)[^\n]|\n(?![ \t]*\n))+?)\*\*")
KEY = re.compile(r"^(ctrl|shift|alt|win|cmd|option)\+", re.I)
PLACEHOLDER = re.compile(r"%(?:\d+\$)?[sd]")


def normalize(text: str) -> str:
    text = html.unescape(text)
    text = text.replace("’", "'").replace("‘", "'")
    text = text.replace('\\"', '"').replace("\\'", "'")
    text = re.sub(r"\s+", " ", text).strip()
    return text.rstrip(":").strip().lower()


def blank(match: re.Match) -> str:
    """Blank out a span but keep its newlines, so line numbers stay right."""
    return re.sub(r"[^\n]", " ", match.group(0))


def strip_code(text: str) -> str:
    text = re.sub(r"^([ \t]*)(```|~~~).*?^\1\2[^\n]*$", blank, text, flags=re.S | re.M)
    text = re.sub(r"<!--.*?-->", blank, text, flags=re.S)
    return re.sub(r"`[^`\n]*`", blank, text)


def read(path: str) -> str:
    with open(path, encoding="utf-8", errors="ignore") as handle:
        return handle.read()


def source_files(tops: list[str]) -> list[str]:
    """Each source file under these folders, or the top itself when it is a
    file, such as the intelis command."""
    found = []
    for top in tops:
        path = os.path.join(ROOT, top)
        if os.path.isfile(path):
            found.append(path)
            continue
        for directory, dirs, files in os.walk(path):
            dirs[:] = [d for d in dirs if d not in ("vendor", "node_modules", ".git")]
            for name in files:
                if name.endswith(SOURCE_EXT) or "." not in name:
                    found.append(os.path.join(directory, name))
    return [path for path in found if os.path.getsize(path) <= 5_000_000]


def po_strings(text: str) -> list[str]:
    """Every msgid and msgstr of a .po file, joined when they wrap."""
    entries = re.finditer(r'^msg(?:id|str) ((?:".*"\n?)+)', text, re.M)
    return ["".join(re.findall(r'"(.*)"', entry.group(1))) for entry in entries]


def source_corpus() -> str:
    parts = []
    for path in source_files(SOURCES):
        text = read(path)
        parts.append(normalize(text))
        if path.endswith(".po"):
            parts.extend(normalize(string) for string in po_strings(text))
    return "\n".join(parts)


def as_pattern(pieces: list[str]) -> re.Pattern | None:
    """The literal pieces of a string, with anything allowed between them.

    None when the pieces hold fewer than two words, because a string such as
    "%s" or "$name: $value" would then match any label at all.
    """
    pieces = [normalize(piece) for piece in pieces]
    if len(re.findall(r"[^\W\d_]{2,}", " ".join(pieces))) < 2:
        return None
    return re.compile("^" + ".+?".join(re.escape(piece) for piece in pieces) + "$")


def placeholder_patterns() -> list[re.Pattern]:
    """Strings that are filled in when shown, as patterns that match them filled.

    Translated strings with %s or %d, and script and PHP strings with a
    variable in them, such as "Type ${CHOSEN} to confirm".
    """
    patterns = []
    variable = re.compile(r"\$\{[^}\n]*\}|\$[A-Za-z_]\w*")
    quoted = re.compile(r'"([^"\n]{6,300})"')
    for path in source_files(["app", "bin", "scripts", "intelis"]):
        text = read(path)
        if path.endswith(".po"):
            for string in po_strings(text):
                if PLACEHOLDER.search(string):
                    patterns.append(as_pattern(PLACEHOLDER.split(string)))
            continue
        if not (path.endswith((".sh", ".php")) or "." not in os.path.basename(path)):
            continue
        for found in quoted.finditer(text):
            string = found.group(1)
            if not variable.search(string):
                continue
            patterns.append(as_pattern(variable.split(string)))
            # The scripts' choose() takes "key:Label:Help" strings.
            for part in string.split(":"):
                if variable.search(part):
                    patterns.append(as_pattern(variable.split(part)))
    return [pattern for pattern in patterns if pattern is not None]


def docs_titles() -> set[str]:
    """Headings, tab titles and box titles on every docs page."""
    titles = set()
    title = re.compile(r'^\s*(?:#{1,6}\s+(.+?)\s*#*$|===\+? "(.+)"|(?:!!!|\?\?\?\+?)\s+\w+\s+"(.+)")')
    for directory, _, files in os.walk(os.path.join(ROOT, "docs")):
        for name in files:
            if name.endswith(".md"):
                for line in read(os.path.join(directory, name)).splitlines():
                    found = title.match(line)
                    if found:
                        text = next(group for group in found.groups() if group)
                        titles.add(normalize(re.sub(r"\*\*|`", "", text)))
    return titles


def allowlist() -> set[str]:
    allowed = set()
    for line in read(ALLOWLIST).splitlines():
        line = line.strip()
        if line and not line.startswith("#"):
            allowed.add(normalize(line))
    return allowed


def pages() -> list[str]:
    found = []
    for folder in FOLDERS:
        directory = os.path.join(ROOT, folder)
        for name in sorted(os.listdir(directory)):
            if name.endswith(".md"):
                found.append(os.path.join(folder, name))
    return found


def main() -> int:
    corpus = source_corpus()
    patterns = placeholder_patterns()
    titles = docs_titles()
    allowed = allowlist()

    def known(label: str) -> bool:
        key = normalize(label)
        if len(key) < 2 or key in allowed or key in titles or key in corpus:
            return True
        if KEY.match(key) or key.endswith("."):
            return True
        return any(pattern.match(key) for pattern in patterns)

    missing = []
    for page in pages():
        text = strip_code(read(os.path.join(ROOT, page)))
        for match in BOLD.finditer(text):
            line = text.count("\n", 0, match.start()) + 1
            for label in PATH_SEPARATOR.split(match.group(1)):
                label = re.sub(r"\s+", " ", label).strip()
                if label and not known(label):
                    missing.append(f'{page}:{line}: "{label}"')

    if missing:
        print("\n".join(missing))
        print()
        print(f"{len(missing)} quoted label(s) not found in the app, the scripts or the translations.")
        print("Fix the page to quote the screen as it is now. If the label is from a screen")
        print("outside InteLIS, such as Windows or the Interface Tool, add it to")
        print("bin/build/docs-labels-allowlist.txt under its heading.")
        return 1
    print(f"Every label quoted in {' '.join(FOLDERS)} exists in the app or the allowlist.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
