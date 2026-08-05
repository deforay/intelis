# Engineering Standards

The bar this codebase is held to, and the review that enforces it.

## 1. Adversarial review

Run before pushing, not after somebody asks:

```bash
bin/dev/review                  # the working branch against master
bin/dev/review <commit-sha>     # one commit
bin/dev/review --uncommitted    # the working tree, before committing
```

The reviewing CLI is named by `$REVIEW_AGENT`, read from the untracked `.env` and falling
back to your shell profile. It is deliberately not written down here: the tool can be
swapped without editing anything, and no vendor name enters the repository. `REVIEW_AGENT`
is a command line rather than a bare binary, so a reviewer that wants a subcommand and one
that wants a flag are both a setting; set `REVIEW_AGENT_STDIN=1` for a reviewer that reads
its prompt from standard input. See `.env.example`.

The review is a local step, not a CI job — the reviewing CLI is authenticated on your
machine. CI enforces the deterministic checks; this one is a discipline.

**The review brief** — single source of truth, extracted verbatim by the script, so it cannot drift:
> "You are reviewing a change to InteLIS, a PHP laboratory information system covering viral load, EID, COVID-19, TB, CD4, hepatitis and custom tests, deployed as a fleet of laboratory instances that sync to a central instance. Do not summarize the code. Find: (1) any query reaching test, patient or user data without the lab scope (`CommonService::labScopeWhere`, `labAdminScopeWhere`, `$_SESSION['labId']`), and any administrative write a restricted operator could reach on a cloud instance (`CommonService::isCloudLisNonAdmin`); (2) SQL built by concatenating request data instead of binding it; (3) anything that can silently lose entered data — a request save that writes result columns, two fields sharing one `name` in a form (PHP keeps the last, so the earlier value is discarded), an index misalignment across parallel POST arrays, a status update that nulls a column it did not intend to touch, or a write to `generic_test_results`, which has no audit triggers and is therefore unrecoverable; (4) schema changes made anywhere but `sys/migrations/`, migrations that are not re-runnable on both fresh and upgraded installs, and a new migration without the matching version bump; (5) any place patient data or a lab identifier is taken from the request rather than from the credential or an explicit allowlist before reaching an API response or a remote payload; (6) user-visible strings that bypass the translation helpers, and output escaped with the wrong helper for its context — HTML body, HTML attribute, JS string, or grid tooltip; (7) a defect fixed in one country form while its siblings carry the same copy-pasted code; (8) tests that assert the happy path but would still pass if the invariant were deleted. Rank findings by severity. If you find nothing in a category, say 'clear' — don't pad."

**Where the second opinion matters most:** anything touching lab scoping or the cloud-instance
admin gate, the result-entry and import paths, every migration, and the remote sync and API
surfaces. Routine CRUD does not need double review — don't ritualize it into overhead.

**Discipline rule:** the same bar applies to every change regardless of how it was written.
Nothing lands on "it runs". The tests and the review pass *are* the bar.

## 2. What counts as a finding

A defect with a failure scenario: concrete inputs or state, and the wrong output or lost data
that results. "This could be cleaner" is not a finding. A trade-off already recorded in the
docs is a rebuttal, not a fix.

Address or explicitly rebut every finding before merging. A rebuttal is a sentence saying why
the code is right, not silence.

## 3. Standing invariants

These are the rules the brief is derived from. They are here so a change can be checked
against them without running a review.

- **Lab scope.** Every read of test, patient or user data on a multi-lab instance goes
  through `labScopeWhere` / `labAdminScopeWhere`. A missing scope is a data-leak bug, not a
  style issue.
- **Schema changes live in `sys/migrations/`.** `sql/init.sql` is a seed for fresh installs
  and is not edited to change the schema; migrations replay on fresh installs too, so they
  must be re-runnable and must not assume an upgraded database. A new migration means a
  version bump in `composer.json` and `version.php`, and `composer update --lock` so the
  lockfile hash stays current.
- **Request saves do not touch result columns.** Add/edit request helpers write request
  fields only. `generic_test_results` has no audit triggers, so a bad write there is
  unrecoverable.
- **One name per field.** Two controls sharing a `name` in one form means PHP keeps the last
  and silently discards the first. Same for a duplicate `id`, which quietly breaks the
  `#id` handler and `label[for]`.
- **Country forms are copies.** A defect found in one country's form is usually present in
  its siblings. Fix the family, not the instance.
- **User-visible strings are translatable** and escaped with the helper matching their output
  context.
- **Exports use OpenSpout.**

## 4. Before you push

- `composer test` green.
- `php -l` clean on every changed PHP file.
- `bin/dev/review` run, and every finding addressed or rebutted.
