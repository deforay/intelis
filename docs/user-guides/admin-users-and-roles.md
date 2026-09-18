# How to manage users and roles

Create a login for each person, and decide what each login can reach. Both live
under **ADMIN → Access Control**.

InteLIS has no self-registration. An administrator creates every login.

## Before starting

- An account with administrator rights
- The role the new user needs. To create one, see [Add or change a role](#add-or-change-a-role)

## Add a user

**Choose the installation type, then follow its steps from top to bottom.**

=== "STS"

    1. Go to **ADMIN → Access Control → Users**.
    2. Select **Add User**.
    3. Enter **Full Name**, **Email** and **Phone Number**. The name appears on
       reports and in the activity log.
    4. Set **Role**.
    5. If the role's access type is Testing Lab, set **Testing Lab** to the lab
       this user works in. The field appears once such a role is selected. It
       limits what the user sees to that lab's work.
    6. Fill in the optional fields the user needs:

        | Field | What to enter |
        | --- | --- |
        | Province/State, District/County | The user's location |
        | Mobile App Access | **Yes** if the user signs in to the mobile app |
        | Interface User Name (from your Molecular testing machine) | The name this person uses on the analyzer. Separate several names with commas |
        | Signature | A signature image for anyone who approves results. It prints on result PDFs |

    7. Enter the **Login ID**.
    8. Enter **Password** and **Confirm Password**, or select **Generate**.
    9. Select **Submit**.
    10. Give the Login ID and password to the user in person. InteLIS asks the
        user to change the password at the first sign-in.

    ??? info "Login ID and password rules"

        The Login ID accepts lowercase letters, numbers, hyphens (-) and
        underscores (_). It accepts no spaces and no capitals.

        The password needs at least 8 characters, with at least one number and
        one letter. Special characters are allowed.

    ??? info "To limit a facility user to their own facilities"

        The Add User form has no facility selector. Once the user is saved, follow
        [Limit a user to certain facilities](#limit-a-user-to-certain-facilities).

=== "LIS or standalone"

    1. Go to **ADMIN → Access Control → Users**.
    2. Select **Add User**.
    3. Enter **Full Name**, **Email** and **Phone Number**. The name appears on
       reports and in the activity log.
    4. Set **Role**. There is no **Testing Lab** field. InteLIS assigns every
       user to this installation's lab.
    5. Fill in the optional fields the user needs:

        | Field | What to enter |
        | --- | --- |
        | Province/State, District/County | The user's location |
        | Mobile App Access | **Yes** if the user signs in to the mobile app |
        | Interface User Name (from your Molecular testing machine) | The name this person uses on the analyzer. Separate several names with commas |
        | Signature | A signature image for anyone who approves results. It prints on result PDFs |

    6. Enter the **Login ID**.
    7. Enter **Password** and **Confirm Password**, or select **Generate**.
    8. Select **Submit**.
    9. Give the Login ID and password to the user in person. InteLIS asks the
       user to change the password at the first sign-in.

    ??? info "Login ID and password rules"

        The Login ID accepts lowercase letters, numbers, hyphens (-) and
        underscores (_). It accepts no spaces and no capitals.

        The password needs at least 8 characters, with at least one number and
        one letter. Special characters are allowed.

=== "Cloud"

    Use this when signed in to the STS with a testing-lab role other than the
    super administrator.

    1. Go to **ADMIN → Access Control → Users**.
    2. Select **Add User**.
    3. Enter **Full Name**, **Email** and **Phone Number**. The name appears on
       reports and in the activity log.
    4. Set **Role**. The list offers testing-lab roles only. It leaves out the
       super administrator role and the API role.
    5. Set **Testing Lab**. The list offers only the administrator's own lab.
    6. Fill in the optional fields the user needs:

        | Field | What to enter |
        | --- | --- |
        | Province/State, District/County | The user's location |
        | Mobile App Access | **Yes** if the user signs in to the mobile app |
        | Interface User Name (from your Molecular testing machine) | The name this person uses on the analyzer. Separate several names with commas |
        | Signature | A signature image for anyone who approves results. It prints on result PDFs |

    7. Enter the **Login ID**.
    8. Enter **Password** and **Confirm Password**, or select **Generate**.
    9. Select **Submit**.
    10. Give the Login ID and password to the user in person. InteLIS asks the
        user to change the password at the first sign-in.

    ??? info "Login ID and password rules"

        The Login ID accepts lowercase letters, numbers, hyphens (-) and
        underscores (_). It accepts no spaces and no capitals.

        The password needs at least 8 characters, with at least one number and
        one letter. Special characters are allowed.

## Limit a user to certain facilities

This applies to the STS only. Use it for facility staff who register their own
requests, so each sees only their own facility.

1. Go to **ADMIN → Access Control → Users**.
2. Select **Edit** on the user.
3. Under **Map User to Selected Facilities (optional)**, move the facilities
   into the selected list.
4. Select **Submit**.
5. Ask the user to open the request form. Only the mapped facilities are
   offered.

??? info "Empty mapping"

    An empty mapping means no facility limit. Leave it empty for lab staff who
    must see every facility. The Testing Lab still applies.

## Reset a user's password

1. Go to **ADMIN → Access Control → Users**.
2. Select **Edit** on the user.
3. Enter **Password** and **Confirm Password**, or select **Generate**.
4. Select **Submit**.
5. Give the new password to the user in person. InteLIS asks the user to
   change it at the next sign-in.

## Give a user an API token

Systems that connect through the API use a token instead of a password.

1. Go to **ADMIN → Access Control → Users**.
2. Select **Edit** on the user.
3. Set **Role** to the API role. The **AuthToken** field appears.
4. Select **Generate Another Token**.
5. Select **Submit**.
6. Copy the token from **AuthToken** into the connecting system.

??? warning "A new token stops the old one at once"

    Anything still using the previous token stops working when the new token
    is saved.

??? info "On a cloud instance"

    The role list of a lab administrator leaves out the API role. The national
    administrator creates API accounts.

## Disable a departing user

1. Go to **ADMIN → Access Control → Users**.
2. Select **Edit** on the user.
3. Set **User Status** to **Inactive**.
4. Select **Submit**.

**User Status** exists on Edit User only. A new user is always saved as active.

??? info "What disabling does"

    The Login ID stops signing in. InteLIS also clears the password and any API
    token. To bring the user back, set **User Status** to **Active** and give
    the user a new password.

    Do not delete the account, and do not give the Login ID to someone else.
    The records the user created stay attached to their name.

## Add or change a role

A role is a named set of privileges. Users get privileges from their role only.

1. Go to **ADMIN → Access Control → Roles**.
2. Select **Add Role**, or **Edit** on an existing role.
3. Enter **Role Name** and **Role Code**. The code must be unique.
4. Set **Landing Page**. Users with this role open on it after signing in.
5. Set **Status** to **Active**.
6. Set **Access Type**: **Testing Lab** for lab staff, **Collection Site** for
   facility staff. Set it before the privileges. It hides the pages that do not
   belong to that type, and InteLIS denies hidden pages on save.
7. Under **Privileges**, open each module's panel and switch on each page this
   role needs. Use **Search permissions...** to find a page.
8. Select **Submit**.

??? info "Which roles hold a privilege"

    On the Roles page, open **Advanced Search** and set **Permission**. The list
    shows only the roles that hold it.

??? info "The super administrator role"

    The first role holds every privilege, whatever its privilege list shows.
    Its access cannot be narrowed.

??? warning "Keep entry and approval apart"

    Approval is the check on result quality. A role that can both enter and
    approve results lets one person sign off their own work.

## Confirm it worked

| Change | Check |
| --- | --- |
| New user | The user signs in, changes the password and sees the expected menu |
| Testing Lab set | The user sees their own lab's samples and no others |
| Facility mapping | The request form offers only the mapped facilities |
| New or changed role | A user holding the role sees the expected pages |
| Disabled user | The Login ID no longer signs in |
