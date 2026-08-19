# How to run the administration training

This guide runs a training session on the ADMIN section of InteLIS. It is
written for the trainer, not the trainee.

Administration is the one part of InteLIS that cannot be practised on a live
installation. Role changes, General Configuration, and the Sample ID format take
effect across the whole installation the moment they are saved. A trainee
experimenting with the Sample ID prefix breaks sample coding for every user on
that installation. The session therefore runs on a training installation.

## Before starting

- A training installation, separate from any lab's live installation
- The same modules active on the training installation as on the labs' live installations
- One InteLIS account per trainee on that installation, each with administrator rights
- The training installation seeded with the exercise data listed below
- The [administration job aids](../job-aids/administration-job-aids.html) printed, one set per trainee
- [How to administer InteLIS](../user-guides/administer-intelis.md) available to trainees during the session

## Split the trainees into two groups

Not every administrator needs every page. Train the two levels separately.

| Level | Who | Covers |
|---|---|---|
| Lab administrator | The designated administrator at each lab | Users, facilities, instruments, module config lists, Interface Tool connections, Monitoring |
| National administrator | Two or three people in total | All of the above, plus roles and privileges, General Configuration, Geographical Divisions |

Most of the trouble in the field comes from national-level settings being changed
by lab-level staff. Making the split explicit in the training is the control.
InteLIS does not enforce it unless the roles are built to.

Run session 1 for both groups. Run session 2 for the national group only.

The ADMIN menu shows a config section only for the modules the installation
runs. Skip the sections for modules the labs do not use.

## Seed the training installation

Each trainee needs records to work on that are not shared with the next trainee.
Before the session, load the training installation with the following.

| Record | How many | Why it is needed |
|---|---|---|
| Provinces | 2 | So a district can be added under a parent, and the failure mode shown |
| Districts | 4 | The facility exercise needs somewhere to put a facility |
| Health facilities | 6 | At least one ticked for a single test type, to demonstrate the Test Type rule |
| Testing labs | 2 | The Interface Tool exercise needs a lab to generate a code against |
| Roles | 3 | Data entry, technician, supervisor, so privileges can be compared |
| Registered requests | 20 or more, at mixed statuses | The Monitoring pages show nothing on an empty installation |

Register the requests under more than one user account, and approve some of
them. The Audit Trail and User Activity Log exercises need a history to read.

## Session 1: the lab administrator

Allow three hours. Demonstrate each task once, then have every trainee perform
it on their own account before moving on.

| Block | Task | Exercise |
|---|---|---|
| 30 min | The ADMIN menu, and the two administrator levels | Open each section. Name what lives in it |
| 40 min | Add a user | Create a user, sign in as that user, confirm the menu matches the role |
| 40 min | Add a facility | Create a facility ticked for one test type only. Find it on that test type's request form. Confirm it is absent from the others |
| 30 min | Maintain the module config lists | Add a sample type under one module's config section. Confirm it is absent from another module's request form. Add it under that module too |
| 20 min | Retire a list entry | Set an entry inactive. Confirm it leaves the form and stays readable on an existing record |
| 20 min | Add an analyzer, connect the Interface Tool | Add an instrument. Generate a connection code. Read the Last Seen column |
| 20 min | Monitoring | Find who changed a given result, using the Audit Trail |

## Session 2: the national administrator

Allow two hours. This group has already done session 1.

| Block | Task | Exercise |
|---|---|---|
| 40 min | Roles and privileges | Build a role from nothing. Sign in as a user holding it. Use the Permission filter to find every role holding a given page |
| 30 min | Geographical divisions | Add a province, then a district under it. Add a district with no parent. Find it missing from the request form |
| 40 min | General Configuration | Walk the setting groups. Change one appearance setting and observe the effect |
| 10 min | The approval list | Read the list of changes that need agreement before they are made |

Do not have trainees change the Sample ID format, the locking days, or the
approval settings during the session, even on the training installation. The
point of the block is that these changes are agreed first, not that they are
easy to make.

## Verify each trainee

A trainee passes when they perform each task unaided on the training
installation, and the check succeeds.

| Task | Check |
|---|---|
| Add a user | The user signs in and sees the expected menu |
| Add a facility | The facility appears on the request form of each test type it was ticked for |
| Add a list entry | The entry appears in its dropdown on the request form |
| Retire a list entry | The entry leaves the form and stays readable on an existing record |
| Add an analyzer | The analyzer appears in Testing Platform when creating a batch |
| Connect the Interface Tool | The installation shows under Connected Installations with a recent Last Seen |
| Build a role | A user holding the role sees only the pages the role was given |
| Find a change in the Audit Trail | The trainee names the field, the old value, the new value, and the user |

Trainees who cannot complete the facility exercise or the list exercise will
generate support requests within the month. Both failures produce the same
complaint, that something is "missing" from the request form.

## After the session

- Give each trainee the printed job aids to take back to their lab
- Record which trainees passed which level, and give the national level to the agreed two or three only
- Remove the training accounts from the training installation, or reset it before the next session
- Confirm that no trainee holds administrator rights on a live installation unless that is their role
