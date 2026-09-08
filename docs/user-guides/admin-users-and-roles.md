# How to manage users and roles

This guide creates logins and decides what each login can reach. Both live under
**ADMIN → Access Control**.

InteLIS has no self-registration. Every login is created by an administrator.

## Before starting

- An account with administrator rights
- The role the new user needs, created already

## Add a user

1. Go to **ADMIN → Access Control → Users**.
2. Select **Add User**.
3. Fill in the details.

| Field | What to enter |
|---|---|
| Full Name | The user's name as it appears on reports and in the activity log |
| Email | The user's address. It must not already be in use |
| Phone Number | The user's number |
| Role | The role that sets what this user can reach |
| Testing Lab | The lab this user operates as. Shown on STS only, and only once a testing-lab role is selected |
| Province/State, District/County | The user's location |
| Mobile App Access | Whether the account can use the mobile app |
| Interface User Name | The user name this person holds on the molecular testing machine. It matches analyzer results to a person |
| Signature | A signature image for anyone who approves results. 100 by 100 pixels |
| Login Id | The identifier the user signs in with |
| Password, Confirm Password | The starting password |
| User Status | Active or inactive |

4. Select **Submit**.
5. Give the Login Id and password to the user directly.

The Login Id accepts lowercase letters, numbers, hyphens, and underscores. It
accepts no spaces and no capitals.

The password must be at least 8 characters and must include at least one number
and at least one letter. Special characters are allowed.

### Testing Lab depends on the instance

- **On STS**, set the **Testing Lab** on every testing-lab user. The field appears
  once a role with that access type is selected, and it scopes what the user sees
  to their own lab's work.
- **On a LIS or standalone installation**, the field is not shown at all. Every
  user is assigned to that installation's own lab automatically, so there is
  nothing to set.
- **On a cloud instance**, a lab administrator's users are fixed to that
  administrator's own lab.

## Limit a user to certain facilities

Facility mapping narrows a user further than the testing lab does. Use it for
facility staff who register their own requests, so each sees only their own
facility.

**The Add User form has no facility selector.** The mapping controls exist only
on Edit User, so a user created and left alone has no facility restriction at
all, whatever was intended when the account was made.

1. Create the user as above and select **Submit**.
2. Reopen the same user under **ADMIN → Access Control → Users**.
3. Use **Map User to Selected Facilities** to move the intended facilities into
   the selected list.
4. Select **Submit**.
5. Sign in as that user, or check their request list, and confirm only the
   intended facilities appear.

Leave the mapping empty for lab staff who should see every facility. An empty
mapping means no facility limit, and the testing lab still applies.

## Give a user an API token

Users who connect through the API need a token rather than a password.

The **AuthToken** field is hidden unless the account's role is the API role, or
the account already holds a token. Opening an ordinary user does not show it.

1. Open the user under **ADMIN → Access Control → Users**.
2. Set **Role** to the API role. The **AuthToken** field appears.
3. Select **Generate**, or **Generate Another Token** to replace the current one.
4. Select **Submit**.

On a cloud instance, a lab administrator cannot assign the API role, so these
accounts are created by a full administrator.

Generating another token invalidates the previous one at once. Anything still
using the old token stops working.

## Disable a departing user

1. Open the user.
2. Set **User Status** to inactive.
3. Select **Submit**.

Do not delete the account, and do not reuse the Login Id for someone else. The
records the user created stay attached to their name.

## Add or change a role

A role is a named set of permissions. Users get their permissions from their
role, never individually.

1. Go to **ADMIN → Access Control → Roles**.
2. Select **Add Role**, or select **Edit** on an existing role.
3. Fill in the details.

| Field | What to enter |
|---|---|
| Role Name | A name staff recognise, such as Lab Technician |
| Role Code | A short unique code |
| Access Type | **Testing Lab** for lab staff. **Collection Site** for facility staff |
| Status | Active or inactive |
| Privileges | Tick each page this role can reach |

4. Select **Submit**.

## How the privilege list works

The privilege list is one collapsible panel per module. Each panel holds that
module's pages, and each page carries a yes or no switch.

**Access Type** filters the list. A page that belongs to lab work disappears when
Access Type is set to Collection Site, and the reverse. Hidden pages are forced
to deny, and the server enforces that on save. Set Access Type first, then set
the privileges.

Use the search box to find a page. Do not scroll the list.

Give each role the permissions its work needs and no more. Approval is the check
on result quality. A role that can both enter and approve its own results removes
that check.

To find which roles hold a given permission, use the **Permission** filter on the
Roles page.

The first role is the super administrator. It holds every permission, and its
permissions cannot be removed.

## Confirm it worked

| Change | Check |
|---|---|
| New user | The user signs in and sees the expected menu |
| Testing lab set | The user sees their own lab's samples and no others |
| Facility mapping | The user sees only the mapped facilities on the request form |
| New or changed role | Sign in as a user holding it, or use the Permission filter on the Roles page |
| Disabled user | The Login Id no longer signs in |
