# Task 10: Roles and Permissions

## Objective

Turn the roles displayed under Business Setup into meaningful access-control rules that clearly answer:

> What can a user with this role see and do, and in which branches can they do it?

The existing role name and slug are useful identifiers, but a role must have enforceable permissions before it can control access.

## Current State

Storeboot already has:

- Tenant-scoped roles.
- System and custom role classifications.
- A permissions table.
- A role-to-permission relationship.
- A role assignment on each tenant membership.
- An optional branch assignment on each tenant membership.

However, custom role creation currently saves only the role name and slug. It does not assign permissions. Most application actions also do not yet enforce role permissions consistently.

Therefore, the current **System** and **Custom** labels describe how a role was created, not what the role is allowed to do.

## Recommended Access-Control Model

Use role-based access control with two separate dimensions:

1. **Role permissions:** What can this user do?
2. **Branch scope:** Where can this user do it?

For example:

- A Branch Manager may manage sales and inventory only in the Ikeja branch.
- An Accountant may access finance across all branches.
- A Cashier may process sales only in their assigned branch.

Branch assignment must not replace permissions, and permissions must not bypass branch restrictions.

## Roles Tab

Keep role management under the **User roles** tab in Business Setup.

The tab should display:

- Role name.
- Role description.
- Whether it is a system role or custom role.
- A concise summary of what the role can do.
- The number of users assigned to the role.
- An action to view its complete permissions.
- An action to edit permissions where editing is allowed.
- An action to duplicate a role.
- Deletion for eligible custom roles that are not in use.

The existing **Add role** button should be renamed to:

> Create custom role

It should create a complete role with permissions, rather than only collecting a name and slug.

## Creating a Custom Role

The recommended workflow is:

1. Enter the role name.
2. Enter a short role description.
3. Optionally choose a predefined role as a starting template.
4. Select access levels for each module.
5. Configure sensitive permissions and limits.
6. Review the generated “What this role can do” summary.
7. Save the role.
8. Assign users to it.

Example:

> **Cashier**
>
> Can create sales, receive payments, and print receipts in assigned branches. Cannot view product costs, issue refunds, void completed sales, manage inventory, or access financial reports.

## Default Role Templates

Provide useful predefined roles with actual permission assignments:

### Business Owner

- Full access across the business.
- Can manage branches, users, roles, subscriptions, billing, and business settings.
- Protected from deletion.
- Its essential permissions cannot be removed.

### Branch Manager

- Manages normal operations within assigned branches.
- Can manage sales, customers, inventory, and branch staff as configured.
- Does not automatically receive business-owner, billing, or unrestricted role-management permissions.

### Cashier / Sales Staff

- Can use the POS.
- Can create sales and receive payments.
- Can print receipts.
- Cannot automatically view costs, issue refunds, void completed transactions, or access finance and payroll.

### Accountant

- Can manage finance, expenses, journals, payment accounts, and financial reports as configured.
- Does not automatically manage users, roles, payroll, or operational settings.

### Inventory Officer

- Can view products and stock.
- Can receive, transfer, count, and adjust inventory as configured.
- Does not automatically gain finance, payroll, or user-management access.

### HR / Admin Officer

- Can manage staff records and designated payroll functions.
- Does not automatically receive finance, sales, inventory, billing, or role-management access.

System roles should serve as safe templates. The Business Owner role should remain protected. Other templates may be editable with safeguards, or users may duplicate them to create a custom variation.

## Permission Levels

Avoid presenting hundreds of checkboxes as the primary interface. Give each module a simple access level:

| Level | Meaning |
|---|---|
| None | The module and its actions are unavailable. |
| View | The user can view permitted records but cannot change them. |
| Operate | The user can perform normal day-to-day work. |
| Manage | The user can manage the module's normal records and configuration. |

Example role matrix:

| Module | None | View | Operate | Manage |
|---|---:|---:|---:|---:|
| Sales |  |  | Yes |  |
| Inventory |  | Yes |  |  |
| Finance | Yes |  |  |  |
| Users and roles | Yes |  |  |  |

The module levels are interface conveniences and permission bundles. Storeboot should ultimately save and enforce individual atomic permissions.

## Sensitive Actions

Some capabilities are too risky to be included automatically in a general View, Operate, or Manage level. They must appear in a clearly marked **Sensitive actions** section.

Examples include:

- Issuing refunds.
- Voiding completed sales.
- Overriding product prices.
- Applying discounts above the standard limit.
- Viewing product costs and profit.
- Adjusting inventory.
- Posting journal entries.
- Approving expenses or financial transactions.
- Running or approving payroll.
- Exporting financial, customer, staff, or payroll data.
- Managing branches.
- Managing users.
- Managing roles and permissions.
- Managing subscriptions, billing, and business-wide settings.

For example, setting Sales to **Operate** may include:

- View sales.
- Create sales.
- Receive payments.
- Print receipts.

It should not automatically include:

- Issue refunds.
- Void completed sales.
- Override prices.
- Apply unrestricted discounts.
- View product costs and profit.

Each sensitive permission should have a short explanation of its effect. High-impact selections should display a warning before the role is saved.

## Permission Limits and Approval Workflows

A simple allowed/not-allowed choice is insufficient for some actions. Sensitive permissions should support restrictions where appropriate.

Examples:

- Maximum discount percentage.
- Maximum refund amount.
- Maximum inventory-adjustment value.
- Ability to prepare a journal entry without posting it.
- Ability to prepare payroll without approving it.
- Ability to invite users without assigning Owner access.

Useful permission outcomes are:

1. Not allowed.
2. Allowed within a defined limit.
3. Allowed without a limit.

Some actions should support approval workflows:

- A Cashier requests a refund.
- A Branch Manager or another authorized user approves it.
- Storeboot records both the requester and approver.

This supports separation of duties and reduces fraud and accidental loss.

## Atomic Permission Catalogue

Store individual permissions using stable, action-oriented slugs. Examples:

### Sales

- `sales.view`
- `sales.create`
- `sales.update`
- `sales.payments.receive`
- `sales.refunds.request`
- `sales.refunds.approve`
- `sales.refunds.issue`
- `sales.orders.void`
- `sales.prices.override`
- `sales.discounts.override`
- `sales.costs.view`

### Inventory

- `inventory.view`
- `inventory.receive`
- `inventory.transfer`
- `inventory.count`
- `inventory.adjust`
- `inventory.adjustments.approve`

### Finance

- `finance.view`
- `finance.expenses.create`
- `finance.expenses.approve`
- `finance.journals.create`
- `finance.journals.post`
- `finance.reports.view`
- `finance.reports.export`

### HR and Payroll

- `hr.staff.view`
- `hr.staff.manage`
- `payroll.prepare`
- `payroll.run`
- `payroll.approve`
- `payroll.export`

### Administration

- `branches.manage`
- `users.invite`
- `users.manage`
- `roles.manage`
- `business.settings.manage`
- `subscriptions.manage`
- `billing.manage`
- `data.export`

The precise catalogue should be finalized by auditing every Storeboot route and application action.

## Effective Permission Summary

The role editor should generate a plain-language summary from the selected permissions and limits.

Example:

> Can create and manage sales in assigned branches, receive payments, and apply discounts up to 10%. Can request refunds up to ₦50,000 but cannot approve them. Can view inventory but cannot adjust stock. Has no access to finance, payroll, users, roles, billing, or business settings.

This summary should be visible:

- Before saving the role.
- On the role list.
- When assigning a role to a user.
- When reviewing a user's access.

## Enforcement Requirements

Permissions must be enforced on the server for every protected action.

The system must:

- Check the user's active tenant membership.
- Check the role's effective permission.
- Check the active or assigned branch scope.
- Check any permission limit.
- Check whether approval is required.
- Reject unauthorized requests even if a user manually calls an endpoint.

The interface should also hide or disable unavailable navigation items, buttons, and fields, but interface visibility must never be the only security control.

Subscription module access and user authorization are different concerns:

- A subscription determines whether a module is available to the tenant.
- A role determines whether a particular user can access or act within that module.

Both checks may be required.

## Permission-Granting Rules

To prevent privilege escalation:

- Only the Business Owner or a user with role-management permission may create or edit roles.
- A user must not grant a permission they do not possess.
- A branch-scoped administrator must not grant access outside their own permitted scope.
- A user must not promote themselves beyond their current authority.
- Assigning Business Owner access should require stronger confirmation.

## Lockout Protection

Storeboot must prevent a tenant from losing all administrators:

- There must always be at least one active Business Owner or full administrator.
- The final Business Owner cannot be deleted, deactivated, or demoted.
- A user cannot remove their own final user-management or role-management access.
- The protected Business Owner role cannot be deleted.

## Audit Trail

Record security-relevant events, including:

- Role created, edited, duplicated, or deleted.
- Permissions added or removed.
- Limits changed.
- Role assigned to or removed from a user.
- Branch access changed.
- Sensitive action requested, approved, rejected, or performed.

Each record should identify:

- Tenant.
- Acting user.
- Affected user or role.
- Before and after values where relevant.
- Branch scope.
- Timestamp.
- Request or transaction reference where relevant.

## Suggested Delivery Sequence

### Phase 1: Permission foundation

- Finalize the atomic permission catalogue.
- Seed permissions.
- Assign permissions to the default role templates.
- Add a central permission-checking service.
- Define Business Owner bypass and lockout safeguards.

### Phase 2: Role-management interface

- Replace the name-only role form with the role editor.
- Add module access levels.
- Add sensitive-action controls.
- Add role descriptions and generated summaries.
- Support editing and duplicating roles.

### Phase 3: Backend enforcement

- Audit every protected route and action.
- Apply tenant, role, permission, and branch checks.
- Add tests proving unauthorized users cannot call protected endpoints.

### Phase 4: Limits and approvals

- Add discount, refund, and adjustment limits.
- Add prepare-versus-approve workflows.
- Add approval queues and status notifications.

### Phase 5: Audit and access review

- Add the security audit trail.
- Add a user access summary.
- Add a role usage view showing assigned users.
- Add warnings for risky permission combinations.

## Acceptance Criteria

- Every role clearly communicates what its users can do.
- Default roles have meaningful predefined permissions.
- A custom role cannot be saved without an explicit permission configuration.
- Sensitive actions are separate from general module access levels.
- Permission limits and approval requirements are enforced where configured.
- Permissions are enforced server-side, not only through hidden interface elements.
- Branch scope is enforced independently of role permissions.
- Unauthorized endpoints return an appropriate denial response.
- At least one full Business Owner remains active.
- Permission and role changes are auditable.
- Users can review a plain-language summary before assigning a role.
