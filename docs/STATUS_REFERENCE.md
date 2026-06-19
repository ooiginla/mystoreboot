# Storeboot Status Reference

Storeboot uses backed PHP enums for core SaaS statuses. Database columns remain strings for portability, while application code uses enums for consistency.

## Tenant Status

Enum: `Modules\Tenancy\Enums\TenantStatus`

| Status | Meaning | Tenant access |
| --- | --- | --- |
| `trialing` | Organization is in its trial period. | Allowed |
| `active` | Organization is active and can use enabled modules. | Allowed |
| `inactive` | Organization is temporarily disabled but retained. | Blocked |
| `suspended` | Organization access is blocked by billing, abuse, or admin action. | Blocked |
| `cancelled` | Organization subscription or account relationship has ended. | Blocked |

## Subscription Status

Enum: `Modules\Subscriptions\Enums\SubscriptionStatus`

| Status | Meaning | Module access |
| --- | --- | --- |
| `trialing` | Free trial is running. | Allowed |
| `active` | Subscription is valid and current. | Allowed |
| `past_due` | Payment failed or renewal is overdue. | Allowed temporarily |
| `grace_period` | Access is temporarily allowed until the current period ends. | Allowed temporarily |
| `paused` | Subscription is paused. | Read-only recommended |
| `suspended` | Subscription access is blocked. | Blocked |
| `cancelled` | Subscription has been cancelled. | Blocked after period end |
| `expired` | Trial or subscription period has ended. | Blocked |

## Membership Status

Enum: `Modules\Access\Enums\MembershipStatus`

| Status | Meaning | Tenant-user access |
| --- | --- | --- |
| `invited` | User has been invited but has not accepted yet. | Blocked |
| `active` | User can access this organization. | Allowed |
| `inactive` | User is disabled for this organization. | Blocked |
| `suspended` | User is blocked from this organization. | Blocked |

## Access Rules

- Platform admins are identified with `users.is_platform_admin`.
- Tenant users only access organizations where they have an active `tenant_memberships` record.
- Subscription access meaning is centralized in `Modules\Subscriptions\Support\SubscriptionAccess`.
- Tenant access meaning is centralized in `TenantStatus::allowsTenantAccess()`.
- Membership access meaning is centralized in `MembershipStatus::allowsTenantAccess()`.
