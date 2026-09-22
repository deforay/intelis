---
description: Which code an InteLIS installation receives on update, how the stable branch is published, and how INTELIS_TRACK pins a different ref.
audience: [system-admin, developer]
module: [all]
type: reference
platform: ubuntu
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Release tracks

This page describes which code an InteLIS installation receives when it runs
`intelis update`, and how that code is published. It applies to Ubuntu
installations updated through `scripts/upgrade.sh`, as of version 5.7.72.

To update a lab, follow [Updating InteLIS on Ubuntu](guides/updating-intelis-on-ubuntu.md).

## The `stable` branch

Installations follow the `stable` branch of `deforay/intelis`. They do not
follow release tags.

| Property | Value |
| --- | --- |
| Branch | `stable` |
| Advanced by | The **Publish** workflow, `.github/workflows/publish.yml` |
| Trigger | The **Verify** workflow completes successfully for a push to `master` |
| Movement | Fast-forward only. The workflow never force-pushes. |
| Reaches a lab | On that lab's next `intelis update` |

Verify is defined in `.github/workflows/no-conflict-markers.yml`. A commit
passes Verify when all of these hold:

- No file contains a conflict marker.
- Every PHP file parses.
- The DI container compiles, and `composer check-invariants` passes.
- The unit tests pass.
- The Interface API code passes the PSR-12 style check and the OpenAPI file is
  valid YAML.
- A fresh install seeded from `sql/init.sql` migrates up to the current version.
- The integration tests pass against a real MySQL database.

Verify does not run against real data and does not open a browser.

## Publish workflow

| Event | Result |
| --- | --- |
| Verify succeeds for a push to `master` | `stable` fast-forwards to that commit. |
| The commit is already on `stable` | Nothing changes. The run succeeds. |
| The commit subject starts with `[hold]` or `[no-publish]` | The commit is not published. The run succeeds. |
| The marker appears anywhere other than the start of the subject | The commit is published. |
| The commit is not on `master` | The run fails. |
| `stable` and `master` have diverged | The push is rejected. The run fails. |
| `stable` does not exist | The run creates it. |

A later commit that is published also publishes every held commit before it,
because `stable` fast-forwards.

**Publish** can also be started by hand from the repository's **Actions** tab.
It takes one input:

| Name | Type | Description | Default |
| --- | --- | --- | --- |
| `sha` | string | Commit to publish. It must be on `master`. | Tip of `master` |

`stable` cannot move backwards. A published change is withdrawn only by a new
commit that reverts it.

## Version numbers

The version number is the schema and feature level. `composer.json` holds it,
`app/system/version.php` is generated from it, and the database records it in
`system_config.sc_version`. `intelis check` compares the two.

A version number does not control delivery. A commit reaches labs through
`stable` whether or not the version changes. The page footer shows the version
followed by the short commit ID, for example `v5.7.72 (22928fe)`.

## `INTELIS_TRACK`

`INTELIS_TRACK` overrides the ref one installation updates to. The resolver is
`resolve_intelis_ref` in `scripts/shared-functions.sh`.

| Value | Ref used |
| --- | --- |
| unset or `latest` | `refs/heads/stable`. If `stable` does not exist, the newest `vX.Y.Z` tag. If there is no tag, `refs/heads/master`. |
| `stable` | `refs/heads/stable` |
| `master` | `refs/heads/master`. Unverified code. |
| `vX.Y.Z`, for example `v5.7.1` | `refs/tags/vX.Y.Z` |

`intelis update` passes `INTELIS_TRACK` across `sudo`, so it needs no `sudo`
on the command line:

```bash
INTELIS_TRACK=master intelis update
```

Every other entry point, such as `sudo intelis-update` or
`sudo bash upgrade.sh`, needs the variable on the same command line, because
`sudo` resets the environment. To run the updater directly:

```bash
sudo INTELIS_TRACK=master intelis-update -p /var/www/intelis
```

`scripts/remote-backup.sh`, `scripts/restore-backup.sh` and
`scripts/intelis-doctor.sh` set `INTELIS_TRACK=master` for themselves when it is
unset.
