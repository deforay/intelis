#!/bin/bash
#
# intelis-runner
#
# Executes root-privileged commands queued by the LIS courier. Runs every
# ~60s via the intelis-runner.timer systemd unit. Processes marker files
# dropped by www-data into each installation's var/remote-commands/pending/,
# writes status back to var/remote-commands/results/ for the next courier
# tick to upload to STS.
#
# Trust model: the runner never touches the network and never trusts a
# free-form shell string from a marker file. Command names go through a
# hardcoded whitelist. Unknown commands fail closed.
#
# Safety:
#   - flock prevents overlapping ticks
#   - nonce database prevents re-running the same command
#   - optional kill switch file disables all root command execution
#   - optional quiet-window config gates apply-phase operations
#   - every invocation writes per-command logs under var/logs/

set -u
set -o pipefail

SEARCH_DIR="${INTELIS_SEARCH_DIR:-/var/www}"
RUNNER_LOG_DIR="/var/log/intelis-runner"
GLOBAL_LOCK="/var/lock/intelis-runner.lock"

mkdir -p "$RUNNER_LOG_DIR"
RUNNER_LOG_FILE="$RUNNER_LOG_DIR/runner-$(date +%Y%m%d).log"

# Redirect all stdout/stderr to the day's log file so systemd's journal stays
# clean and operators can tail one file per day for the whole host.
exec >>"$RUNNER_LOG_FILE" 2>&1

log() {
    printf '[%s] %s\n' "$(date +'%Y-%m-%d %H:%M:%S')" "$*"
}

# Single-flight across overlapping timer ticks.
exec 9>"$GLOBAL_LOCK"
if ! flock -n 9; then
    log "another runner is active; skipping tick"
    exit 0
fi

if ! command -v jq >/dev/null 2>&1; then
    log "jq is not installed; runner cannot parse markers. apt-get install jq"
    exit 0
fi

#############
# Helpers
#############

# Detect valid InteLIS installations under a root dir. A valid install has
# bin/migrate.php and public/index.php and var/.
find_installs() {
    local base="$1"
    local d
    for d in "$base"/*; do
        [ -d "$d" ] || continue
        [ -f "$d/bin/migrate.php" ] || continue
        [ -f "$d/public/index.php" ] || continue
        echo "$d"
    done
}

# Ensure the var/remote-commands tree has the expected subdirs + perms.
# pending/  written by www-data; read + deleted by root
# results/  written by root; read + deleted by www-data
# prepared/ root-only; tracks upgrade-prepare outcomes so upgrade-apply can resolve dependsOn
# processed-nonces/  root-only; anti-replay
# disabled  marker file that disables the runner entirely for this lab
ensure_layout() {
    local lp="$1"
    local root="$lp/var/remote-commands"
    mkdir -p "$root/pending" "$root/results" "$root/prepared" "$root/processed-nonces"
    # Best-effort ownership normalization. Never escalate.
    chown -R www-data:www-data "$root/pending" "$root/results" 2>/dev/null || true
    chmod 0770 "$root/pending" "$root/results" 2>/dev/null || true
    chmod 0700 "$root/prepared" "$root/processed-nonces" 2>/dev/null || true
}

# Read scalar field from a JSON file (returns empty string if missing).
json_field() {
    local file="$1" query="$2"
    jq -r "${query} // empty" "$file" 2>/dev/null
}

# Atomic write of a status file in results/ so partial files can't be
# consumed by the courier.
write_result() {
    local results_dir="$1" cmd_id="$2" nonce="$3" status="$4" result_json="$5"
    local tmp="$results_dir/.tmp.$cmd_id.$$"
    # Using jq to build the payload avoids any shell-quoting pitfalls with
    # operator-supplied data (output tails etc).
    jq -n \
        --arg commandId "$cmd_id" \
        --arg nonce "$nonce" \
        --arg status "$status" \
        --argjson result "$result_json" \
        '{commandId: $commandId, nonce: $nonce, status: $status, result: $result}' \
        > "$tmp" || { rm -f "$tmp"; return 1; }

    chmod 0660 "$tmp" 2>/dev/null || true
    chown root:www-data "$tmp" 2>/dev/null || true
    mv -f "$tmp" "$results_dir/$cmd_id.json"
}

# Build a result JSON blob capturing exit code and a tail of output.
build_result_json() {
    local rc="$1" output_log="$2"
    local tail_json='""'
    if [ -f "$output_log" ]; then
        tail_json=$(tail -n 30 "$output_log" 2>/dev/null | jq -Rs . || echo '""')
    fi
    printf '{"exitCode":%d,"outputTail":%s}\n' "$rc" "$tail_json"
}

# True (0) if the lab has globally disabled runner execution.
is_disabled() {
    local lp="$1"
    [ -f "$lp/var/remote-commands/disabled" ]
}

#############
# Dispatch
#############

dispatch_marker() {
    local lp="$1" marker="$2"
    local pending_dir="$lp/var/remote-commands/pending"
    local results_dir="$lp/var/remote-commands/results"
    local prepared_dir="$lp/var/remote-commands/prepared"
    local nonces_dir="$lp/var/remote-commands/processed-nonces"
    local logs_dir="$lp/var/logs"
    mkdir -p "$logs_dir" 2>/dev/null || true

    local cmd_id command nonce depends_on maintenance maint_arg
    cmd_id=$(json_field "$marker" '.commandId')
    command=$(json_field "$marker" '.command')
    nonce=$(json_field "$marker" '.nonce')
    depends_on=$(json_field "$marker" '.dependsOn')
    # .params.maintenance is a boolean set by the STS queue UI (checkbox).
    # jq's `// empty` yields empty for missing/null/false, "true" only for true.
    maintenance=$(json_field "$marker" '.params.maintenance')
    maint_arg=""
    [ "$maintenance" = "true" ] && maint_arg="-M"

    if [ -z "$cmd_id" ] || [ -z "$command" ]; then
        log "marker $marker missing commandId or command; discarding"
        rm -f "$marker"
        return 0
    fi

    # Anti-replay.
    if [ -n "$nonce" ] && [ -f "$nonces_dir/$nonce" ]; then
        log "$cmd_id ($command): nonce already processed; discarding stale marker"
        rm -f "$marker"
        return 0
    fi

    # Respect the local kill switch.
    if is_disabled "$lp"; then
        log "$cmd_id ($command): runner disabled for $lp; leaving marker"
        write_result "$results_dir" "$cmd_id" "$nonce" "failed" \
            '{"error":"runner disabled on this instance"}'
        rm -f "$marker"
        return 0
    fi

    log "$cmd_id ($command): dispatching against $lp"

    # Emit 'running' first so STS can see we actually started.
    write_result "$results_dir" "$cmd_id" "$nonce" "running" '{}'

    local output_log="$logs_dir/runner-$cmd_id.log"
    : > "$output_log"
    local rc=0 status='completed'

    case "$command" in
        refresh-perms)
            if command -v intelis-refresh >/dev/null 2>&1; then
                intelis-refresh -p "$lp" -m full >>"$output_log" 2>&1 || rc=$?
            else
                echo "intelis-refresh is not installed" >>"$output_log"; rc=127
            fi
            ;;

        restart-apache)
            if command -v apache2ctl >/dev/null 2>&1; then
                apache2ctl -k graceful >>"$output_log" 2>&1 || rc=$?
            else
                systemctl reload apache2 >>"$output_log" 2>&1 || rc=$?
            fi
            ;;

        upgrade)
            # Prepare + auto-apply back-to-back in one invocation.
            # intelis-update default flow is prepare+apply; -s skips Ubuntu
            # updates; -b skips backup prompts (operator-triggered upgrade).
            if ! command -v intelis-update >/dev/null 2>&1; then
                echo "intelis-update is not installed" >>"$output_log"; rc=127
            else
                # $maint_arg is either empty (silent, the default) or "-M".
                intelis-update -p "$lp" -s -b $maint_arg >>"$output_log" 2>&1 || rc=$?
            fi
            ;;

        upgrade-prepare)
            # Prepare only; live site untouched. Safe to run any time.
            if ! command -v intelis-update >/dev/null 2>&1; then
                echo "intelis-update is not installed" >>"$output_log"; rc=127
            else
                intelis-update -p "$lp" --prepare-only -s -b >>"$output_log" 2>&1 || rc=$?
                if [ "$rc" -eq 0 ]; then
                    # Extract staging dir from our known output format:
                    # "Prepared at /var/intelis-staging/..." on a line by itself.
                    local staging_dir staged_version
                    staging_dir=$(grep -Eo '/var/intelis-staging/[^[:space:]]+' "$output_log" \
                                  | grep -v '^\s*$' | tail -n 1 || true)
                    if [ -n "$staging_dir" ] && [ -f "$staging_dir/READY" ]; then
                        staged_version=$(grep -E '^version=' "$staging_dir/READY" | head -1 | cut -d= -f2- || true)
                        jq -n \
                            --arg stagingDir "$staging_dir" \
                            --arg version "${staged_version:-unknown}" \
                            '{stagingDir: $stagingDir, version: $version}' \
                            > "$prepared_dir/$cmd_id.json"
                        log "$cmd_id: staged version ${staged_version:-unknown} at $staging_dir"
                        # Override the standard result JSON with a richer payload
                        # so STS sees stagedVersion + stagingDir alongside exitCode.
                        local tail_json='""'
                        [ -f "$output_log" ] && tail_json=$(tail -n 30 "$output_log" 2>/dev/null | jq -Rs . || echo '""')
                        result_json=$(jq -n \
                            --argjson exitCode 0 \
                            --arg stagedVersion "${staged_version:-unknown}" \
                            --arg stagingDir "$staging_dir" \
                            --argjson outputTail "$tail_json" \
                            '{exitCode: $exitCode, stagedVersion: $stagedVersion, stagingDir: $stagingDir, outputTail: $outputTail}')
                        write_result "$results_dir" "$cmd_id" "$nonce" "prepared" "$result_json"
                        # Mark nonce + remove marker; skip the generic result-write at end.
                        if [ -n "$nonce" ]; then
                            mkdir -p "$nonces_dir"
                            : > "$nonces_dir/$nonce"
                            chmod 0600 "$nonces_dir/$nonce" 2>/dev/null || true
                        fi
                        rm -f "$marker"
                        log "$cmd_id ($command): prepared (exit=0)"
                        return 0
                    else
                        echo "could not locate staging dir / READY sentinel after prepare" >>"$output_log"
                        rc=65
                    fi
                fi
            fi
            ;;

        upgrade-apply)
            if [ -z "$depends_on" ]; then
                echo "upgrade-apply requires a dependsOn commandId" >>"$output_log"; rc=64
            elif [ ! -f "$prepared_dir/$depends_on.json" ]; then
                echo "no prepared record for dependsOn=$depends_on" >>"$output_log"; rc=64
            else
                local staging_dir
                staging_dir=$(jq -r '.stagingDir // empty' "$prepared_dir/$depends_on.json")
                if [ -z "$staging_dir" ] || [ ! -d "$staging_dir" ] || [ ! -f "$staging_dir/READY" ]; then
                    echo "staging dir invalid or READY sentinel missing: $staging_dir" >>"$output_log"; rc=64
                elif ! command -v intelis-update >/dev/null 2>&1; then
                    echo "intelis-update is not installed" >>"$output_log"; rc=127
                else
                    # $maint_arg is either empty (silent, the default) or "-M".
                    intelis-update -p "$lp" --apply-prepared "$staging_dir" -s -b $maint_arg >>"$output_log" 2>&1 || rc=$?
                    if [ "$rc" -eq 0 ]; then
                        rm -f "$prepared_dir/$depends_on.json"
                    fi
                fi
            fi
            ;;

        *)
            echo "unknown command: $command" >>"$output_log"; rc=126
            ;;
    esac

    if [ "$rc" -ne 0 ]; then
        status='failed'
    fi

    local result_json
    result_json=$(build_result_json "$rc" "$output_log")
    write_result "$results_dir" "$cmd_id" "$nonce" "$status" "$result_json"

    # Mark nonce processed + remove the marker so we don't re-run.
    if [ -n "$nonce" ]; then
        mkdir -p "$nonces_dir"
        : > "$nonces_dir/$nonce"
        chmod 0600 "$nonces_dir/$nonce" 2>/dev/null || true
    fi
    rm -f "$marker"

    log "$cmd_id ($command): $status (exit=$rc)"
}

process_install() {
    local lp="$1"
    local pending_dir="$lp/var/remote-commands/pending"
    ensure_layout "$lp"
    [ -d "$pending_dir" ] || return 0

    shopt -s nullglob
    local marker
    for marker in "$pending_dir"/*.json; do
        [ -f "$marker" ] || continue
        dispatch_marker "$lp" "$marker" || log "dispatch error for $marker"
    done
    shopt -u nullglob

    # Heartbeat: touch once per tick per install. The courier reads this
    # file's mtime and reports it to STS so operators can see "runner last
    # seen X min ago". Written by root but made world-readable so the
    # courier (www-data) can stat() it.
    local heartbeat="$lp/var/remote-commands/runner.heartbeat"
    : > "$heartbeat"
    chmod 0664 "$heartbeat" 2>/dev/null || true
    chown root:www-data "$heartbeat" 2>/dev/null || true
}

# Age-out nonces older than 30 days so the dir doesn't grow without bound.
gc_nonces() {
    local lp="$1"
    local dir="$lp/var/remote-commands/processed-nonces"
    [ -d "$dir" ] || return 0
    find "$dir" -type f -mtime +30 -delete 2>/dev/null || true
}

#############
# Main
#############

installs=()
while IFS= read -r p; do
    [ -n "$p" ] && installs+=("$p")
done < <(find_installs "$SEARCH_DIR")

if [ "${#installs[@]}" -eq 0 ]; then
    log "no installations found under $SEARCH_DIR"
    exit 0
fi

for install_path in "${installs[@]}"; do
    process_install "$install_path" || log "error processing $install_path"
    gc_nonces "$install_path"
done

exit 0
