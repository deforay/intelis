#!/bin/bash
set -Eeuo pipefail

# Brings a machine's InteLIS commands up to date, and does nothing else.
#
# To use this script:
#   sudo bash -c "$(curl -fsSL https://raw.githubusercontent.com/deforay/intelis/master/scripts/bootstrap.sh)"
#
# or, with wget:
#   sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/bootstrap.sh)"
#
# Run it once per machine. After it, `intelis update` updates the installation,
# and every other `intelis` command works, on a machine of any age.
#
# Why this exists
#
# /usr/local/bin/intelis used to be a thin composer wrapper, and on a machine
# that has not taken an upgrade since the dispatcher shipped it still is. There,
# `intelis update` means `composer update` — which rewrites composer.lock to
# whatever upstream released that morning and installs the development toolchain
# onto a server running a lab. The command cannot fix itself, because the fixed
# version only arrives with an upgrade that the broken command is what you would
# have used to get.
#
# So this breaks the circle from outside: it installs the current commands
# directly, and then the normal ones are safe to use.
#
# Note the invocation above is `bash -c "$(curl …)"`, not `curl … | bash`.
# Piping would make this script bash's standard input, which is fine here — this
# script asks nothing — but it is the habit that ruins the two scripts it
# installs. Both of those prompt, and a piped run answers their questions with
# their own text. setup.sh refuses to start when piped for exactly that reason.

RAW_BASE="https://raw.githubusercontent.com/deforay/intelis/master"

print() {
  local type=${1:-info}; shift || true
  local message=${1:-};  shift || true
  case "$type" in
    error)   printf "\033[1;91m❌ Error:\033[0m %s\n" "$message" >&2 ;;
    success) printf "\033[1;92m✅ Success:\033[0m %s\n" "$message" ;;
    warning) printf "\033[1;93m⚠️ Warning:\033[0m %s\n" "$message" ;;
    *)       printf "\033[1;96mℹ️ Info:\033[0m %s\n" "$message" ;;
  esac
}

if [ "$(id -u)" -ne 0 ]; then
  print error "Need admin privileges. Run it with sudo."
  exit 1
fi

fetch() { # fetch <url> <destination>
  if command -v curl >/dev/null 2>&1; then
    curl -fsSL -o "$2" "$1"
  elif command -v wget >/dev/null 2>&1; then
    wget -q -O "$2" "$1"
  else
    print error "Neither curl nor wget is installed, so nothing can be downloaded."
    return 1
  fi
}

# install_command <source-path-in-repo> <name-in-/usr/local/bin>
install_command() {
  local src=$1 name=$2 dest="/usr/local/bin/$2" tmp
  tmp="$(mktemp)"

  if ! fetch "${RAW_BASE}/${src}" "$tmp"; then
    rm -f "$tmp"
    print error "Could not download ${name}. Check this machine's internet connection."
    return 1
  fi

  # A download cut short is still a file, and installing half a script gives a
  # syntax error from a line nobody wrote. Cheap to rule out.
  if [ "$(head -c 2 "$tmp")" != '#!' ]; then
    rm -f "$tmp"
    print error "What came back for ${name} is not a script. This machine may be behind a portal that intercepted the download."
    return 1
  fi

  # Replace rather than write through. /usr/local/bin/intelis is a symlink into
  # the installation on machines set up by setup.sh, and writing through it would
  # quietly edit a file inside the install tree instead of the command.
  rm -f "$dest"
  install -m 0755 "$tmp" "$dest"
  rm -f "$tmp"

  print success "${name} installed"
}

printf "\n\033[1;96mUpdating this machine's InteLIS commands\033[0m\n\n"

install_command intelis intelis
install_command scripts/upgrade.sh intelis-update

echo
print success "Done. This machine's commands are current."
echo
print info "Update InteLIS      : intelis update"
print info "Check the machine   : intelis check"
print info "See the menu        : intelis"
echo
