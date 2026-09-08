# Engineering Standards

The bar this codebase is held to, and the review that enforces it.

## 1. Adversarial review

Run before pushing, not after somebody asks:

```bash
bin/dev/review                  # the working branch against master
bin/dev/review <commit-sha>     # one commit
bin/dev/review --uncommitted    # the working tree, before committing
bin/dev/review --verify <...>   # add a second pass that tries to refute the first's findings
```

`--verify` exists because one pass that both finds and reports has nothing pushing back on
it, and that is where the false positives are: a run of this once produced several
sentences resting on a claim about a function it had not opened. The second pass is given
the findings and one job — break them — and reports only what survives, with the file and
line that settles each. It doubles the cost of a run, so it is opt-in; `REVIEW_VERIFY=1`
in `.env` makes it the default.

The reviewing CLI is named by `$REVIEW_AGENT`, read from the untracked `.env` and falling
back to your shell profile. It is deliberately not written down here: the tool can be
swapped without editing anything, and no vendor name enters the repository. `REVIEW_AGENT`
is a command line rather than a bare binary, so a reviewer that wants a subcommand and one
that wants a flag are both a setting; set `REVIEW_AGENT_STDIN=1` for a reviewer that reads
its prompt from standard input. See `.env.example`.

The review is a local step, not a CI job — the reviewing CLI is authenticated on your
machine. CI enforces the deterministic checks; this one is a discipline.

**The review brief** — single source of truth, extracted verbatim by the script, so it cannot drift:
> "You are reviewing a change to InteLIS, a PHP laboratory information system covering viral load, EID, COVID-19, TB, CD4, hepatitis and custom tests, deployed as a fleet of laboratory instances that sync to a central instance. Do not summarize the code. Find: (1) any query reaching test, patient or user data without the lab scope (`CommonService::labScopeWhere`, `labAdminScopeWhere`, `$_SESSION['labId']`), and any administrative write a restricted operator could reach on a cloud instance (`CommonService::isCloudLisNonAdmin`); (2) SQL built by concatenating request data instead of binding it; (3) anything that can silently lose entered data — a request save that writes result columns, two fields sharing one `name` in a form (PHP keeps the last, so the earlier value is discarded), an index misalignment across parallel POST arrays, a status update that nulls a column it did not intend to touch, or a write to `generic_test_results`, which has no audit triggers and is therefore unrecoverable; (4) schema changes made anywhere but `sys/migrations/`, migrations that are not re-runnable on both fresh and upgraded installs, and a new migration without the matching version bump; (5) any place patient data or a lab identifier is taken from the request rather than from the credential or an explicit allowlist before reaching an API response or a remote payload; (6) user-visible strings that bypass the translation helpers, and output escaped with the wrong helper for its context — HTML body, HTML attribute, JS string, or grid tooltip; (7) a defect fixed in one country form while its siblings carry the same copy-pasted code; (8) tests that assert the happy path but would still pass if the invariant were deleted. Carry the evidence: every claim about code names the file and line and quotes the line it rests on. If you cannot find the line that proves a claim, report the finding as UNVERIFIED and say what you looked for -- that is a third outcome alongside a finding and a clear, and it is always preferable to asserting on thin evidence. When a hand-maintained list is missing an entry, trace the whole path and report every entry it is missing, not the first. If the commit message or a comment already addresses a concern, say why that reasoning is insufficient rather than restating the concern. Report a defect outside the diff separately, not ranked among the diff's findings. Rank findings by consequence, not by feel: P1 is a lab losing data or being unable to upgrade, everything else starts at P2. If you find nothing in a category, say 'clear' -- don't pad, and never reach for a finding to avoid an empty category."

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
- **A password handed to MySQL is the weakest of the three ways to give it one.** MySQL reads
  option files before anything passed to it, and the precedence is command line > option file
  > environment. `setup_mysql_config()` writes `/root/.my.cnf` with a password on every
  machine, and these scripts run as root, so a password supplied in `MYSQL_PWD` is silently
  ignored in favour of that file's. Any script shelling out to `mysql`, `mysqldump`,
  `mysqladmin` or `mysqlcheck` with a password of its own passes `--no-defaults` as the
  **first** argument, or MySQL ignores the flag without saying so — and only when a password
  was actually supplied, since an empty one means the option file is the intended source. It
  always presents the same way: a credential that is definitely correct being refused, with
  nothing on screen to explain it. Three occurrences so far — db-tools 3.3.0, `db-backup.sh`,
  and `repair_html_escaped_db_password()`, where it left the repair unable to fire on the
  machines it was written for. Known and deliberately not fixed: the `SET PERSIST sql_mode`
  calls in `setup.sh` and `upgrade.sh`, where the same setting is also written to
  `mysqld.cnf` and the failure is printed rather than swallowed.
- **A migration must survive the table its foreign key points at being absent.** `CREATE
  TABLE` with a `FOREIGN KEY` onto a table that is not there fails with 1824, which is not
  in the runner's benign set, so the migration halts, `sc_version` stays behind and every
  later version is blocked for good. A country deployment that never enabled a module has
  neither its request table nor its result table, and preflight reads exactly that pair as
  a module removed rather than broken — so the state is legitimate and common. The runner
  has no way to express "only if the parent exists", so a repair migration creates the
  table without the constraint and keeps the index the key sat on.
- **`sql/init.sql` is a MySQL 8 dump; README supports MySQL 5.7.** Its column and table
  definitions carry `COLLATE utf8mb4_0900_ai_ci`, which does not exist on 5.7 and fails with
  1273. Never copy a `COLLATE` clause from the seed into a migration: leave it off and each
  server applies its own default for `utf8mb4` — a fresh MySQL 8 install still lands on the
  collation the seed declares — and `composer db:collation` is what brings an installation
  into line.
- **The runner's benign-errno set is the definition of "safe to fail".** 1050, 1060, 1061,
  1068, 1091 and 1826 are swallowed and the migration continues; anything else halts the
  upgrade and strands the instance. Before writing a statement that may fail on some
  installation, check which side of that line its error code falls on.
- **An integration test that owns a database names it with the process id.** Two suites run
  against one MySQL here — a second terminal, or a watcher beside a manual run — and a
  fixed-name database is dropped and recreated underneath the other run, so the fixture is
  gone by the time the assertions read it. It presents as an intermittent failure that looks
  like a test-order bug and disappears on a re-run. `tests/Support/MigrationRunnerFunctions`
  shares one database on purpose, because the runner caches the schema name in a static;
  anything that builds real application tables wants its own.
- **`mysqladmin ping` is not a credential check.** It answers "is the server alive", and
  answers yes when access is denied — which is why it is the right probe for "is MySQL up"
  and useless for "is this password correct". Test a password by running a statement.
- **User-visible strings are translatable** and escaped with the helper matching their output
  context.
- **Exports use OpenSpout.**
- **A caught error still has to reach someone.** `LegacyRequestHandler` discards the page
  buffer on a throw and hands off to `ErrorResponseGenerator`, which renders the styled page
  for a browser, JSON for an AJAX caller, and logs both with an error ID the user can quote.
  A terminal `try/catch` that only logs bypasses all of that and returns a blank 200: the
  page looks like it worked. Log and rethrow, or handle it visibly (flash message plus
  redirect). Swallowing is only correct where the failure is genuinely optional, and that
  belongs in service code, not at the bottom of a page. Log `$e->getFile()`/`$e->getLine()`,
  never `__FILE__`/`__LINE__` — the latter records the catch, not the failure.

## 4. Before you push

- `composer test` green.
- `php -l` clean on every changed PHP file.
- `bin/dev/review` run, and every finding addressed or rebutted.
- Shell scripts under `scripts/` run, not just read. `bash -n` proves syntax and nothing
  else. Use a privileged systemd container and stage the machine the way the fleet actually
  is — MySQL installed, `/root/.my.cnf` present, an installation under `/var/www/intelis` —
  then break the thing the script is supposed to handle and watch it handle it. Every MySQL
  credential bug above survived review and reading, and was found in the first minute of
  running the script on a machine configured like a lab's.
