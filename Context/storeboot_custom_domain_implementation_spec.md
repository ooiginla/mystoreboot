# Storeboot Custom Domain Support — Implementation Specification

## 1. Objective

Storeboot currently supports tenant storefronts using Storeboot-managed subdomains such as:

```text
business_name.storeboot.com
```

The goal is to add support for customer-owned custom domains such as:

```text
www.mybakery.com
shop.mybakery.com
mybakery.com
```

A custom domain must resolve to the correct Storeboot tenant storefront while preserving Storeboot's existing tenant-routing model.

For the first implementation, custom domains should apply only to the **public customer-facing storefront**.

The Storeboot merchant/admin application should remain centralized on a Storeboot-owned domain such as:

```text
app.storeboot.com
```

This avoids unnecessary complexity around authentication, sessions, cookies, CSRF, CORS, and cross-domain merchant access.

---

## 2. Recommended Architecture

Use the following model:

```text
Customer Domain
www.mybakery.com
        |
        v
custom.storeboot.com
        |
        v
Storeboot Nginx
        |
        v
Laravel Application
        |
        v
Tenant Domain Resolver
        |
        v
Correct Tenant Storefront
```

Storeboot should support both:

```text
mybakery.storeboot.com
```

and:

```text
www.mybakery.com
```

as domain aliases belonging to the same tenant.

The customer should be able to select one domain as the **primary storefront domain**.

---

## 3. Domain Types

Storeboot should support at least two domain types:

```text
STOREBOOT_SUBDOMAIN
CUSTOM
```

Examples:

```text
business.storeboot.com     -> STOREBOOT_SUBDOMAIN
www.business.com           -> CUSTOM
shop.business.com          -> CUSTOM
business.com               -> CUSTOM
```

---

## 4. Recommended Database Design

Do not add only one `custom_domain` field directly to the tenant or organisation table.

Create a dedicated table:

```text
tenant_domains
```

This allows one tenant to have several valid hostnames.

### Suggested schema

```sql
CREATE TABLE tenant_domains (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    domain VARCHAR(255) NOT NULL UNIQUE,
    domain_type ENUM('STOREBOOT_SUBDOMAIN','CUSTOM') NOT NULL,
    status ENUM('PENDING','VERIFYING','VERIFIED','ACTIVE','FAILED') NOT NULL DEFAULT 'PENDING',
    verification_token VARCHAR(255) NULL,
    ssl_status ENUM('PENDING','PROVISIONING','ACTIVE','FAILED') DEFAULT 'PENDING',
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    verified_at TIMESTAMP NULL,
    ssl_issued_at TIMESTAMP NULL,
    ssl_expires_at TIMESTAMP NULL,
    activated_at TIMESTAMP NULL,
    last_dns_check_at TIMESTAMP NULL,
    failure_reason TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

If Storeboot uses `organizations`, `businesses`, or another tenant table name, replace `tenant_id` accordingly.

---

## 5. Example Domain Records

Example tenant:

```text
Sweet Crumbs Bakery
```

Domains:

```text
sweetcrumbs.storeboot.com
STOREBOOT_SUBDOMAIN
ACTIVE
is_primary = false
```

```text
www.sweetcrumbs.com
CUSTOM
ACTIVE
is_primary = true
```

Optionally:

```text
sweetcrumbs.com
CUSTOM
ACTIVE
is_primary = false
```

The non-primary domain should redirect to the primary domain.

---

## 6. Customer Settings UI

Add:

```text
Settings -> Domain Settings
```

### Storeboot Domain

Display:

```text
Your Storeboot Domain
sweetcrumbs.storeboot.com
Status: Active
```

The default Storeboot subdomain should continue to work even after a custom domain is enabled unless Storeboot explicitly decides to redirect it.

---

## 7. Custom Domain UI

Add a section:

```text
Connect Your Own Domain
```

Field:

```text
Domain
```

Example:

```text
www.sweetcrumbs.com
```

Button:

```text
Connect Domain
```

After submission, Storeboot should:

1. Normalize the domain.
2. Validate the hostname.
3. Ensure it is not already claimed.
4. Generate a verification token.
5. Save the domain with status `PENDING`.
6. Display DNS instructions.

---

## 8. Domain Normalization

Before saving, normalize customer input.

Input examples:

```text
https://WWW.MyBakery.com/
http://mybakery.com
www.mybakery.com/
```

Stored values should become:

```text
www.mybakery.com
mybakery.com
```

Do not store protocols, paths, query strings, or trailing slashes.

Suggested Laravel logic:

```php
$domain = strtolower(trim($domain));
$domain = preg_replace('#^https?://#i', '', $domain);
$domain = explode('/', $domain)[0];
$domain = rtrim($domain, '.');
```

Reject invalid values such as:

```text
localhost
192.168.1.1
storeboot.com
app.storeboot.com
```

Storeboot-owned domains must be protected from being claimed as custom domains.

---

## 9. Unique Domain Validation

Each hostname must belong to only one tenant.

The `domain` field must remain unique.

If a domain is already claimed, return:

```text
This domain is already connected to another Storeboot business.
```

Do not reveal the identity of the other tenant.

---

## 10. DNS Strategy

Create a dedicated Storeboot hostname such as:

```text
custom.storeboot.com
```

Point it to the Storeboot production server.

Example:

```text
custom.storeboot.com
A
STOREBOOT_SERVER_IP
```

Customer custom domains should point to this hostname whenever possible.

---

## 11. Preferred Customer DNS Setup

For:

```text
www.mybakery.com
```

customer should create:

```text
Type: CNAME
Name: www
Target: custom.storeboot.com
```

For:

```text
shop.mybakery.com
```

customer should create:

```text
Type: CNAME
Name: shop
Target: custom.storeboot.com
```

This is the preferred setup.

---

## 12. Root Domain Support

For:

```text
mybakery.com
```

root-domain configuration depends on the DNS provider.

### Option A — A Record

```text
Type: A
Name: @
Value: STOREBOOT_SERVER_IP
```

### Option B — ALIAS / ANAME / CNAME Flattening

If supported:

```text
mybakery.com -> custom.storeboot.com
```

For the first Storeboot implementation, encourage customers to use:

```text
www.mybakery.com
```

or:

```text
shop.mybakery.com
```

because CNAME configuration is easier and more portable.

---

## 13. Domain Ownership Verification

Do not activate a custom domain merely because a user typed it into Storeboot.

Generate a unique token such as:

```text
storeboot-verification=7f8307c49fa21f2f
```

Ask the customer to create:

```text
Type: TXT
Name: _storeboot
Value: storeboot-verification=7f8307c49fa21f2f
```

This produces:

```text
_storeboot.mybakery.com
TXT
storeboot-verification=7f8307c49fa21f2f
```

---

## 14. Verification Flow

Storeboot should provide:

```text
Verify Domain
```

When clicked, backend should:

1. Resolve the expected TXT record.
2. Confirm the verification token.
3. Confirm the hostname points to Storeboot.
4. Update status.
5. Trigger SSL provisioning.

Suggested state flow:

```text
PENDING
  -> VERIFYING
  -> VERIFIED
  -> SSL PROVISIONING
  -> ACTIVE
```

---

## 15. Suggested UI Status Display

```text
Domain Configuration     ✓
DNS Connection           ✓
Ownership Verification  ✓
SSL Certificate          ⏳
Storefront Status        ⏳
```

Final state:

```text
www.mybakery.com
✓ Connected
✓ Verified
✓ SSL Active
✓ Storefront Active
```

---

## 16. Nginx Configuration Strategy

Storeboot likely already includes something similar to:

```nginx
server_name storeboot.com *.storeboot.com;
```

This does not automatically match custom domains.

A default application server block can route HTTP traffic to Laravel:

```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    server_name _;

    root /var/www/storeboot/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
}
```

Adjust the application path and PHP-FPM socket to the actual production server.

---

## 17. Important Nginx Rule

Do not automatically trust arbitrary domains.

Laravel must validate the request hostname against `tenant_domains`.

If no active tenant domain exists, return a controlled invalid-domain response or redirect to the Storeboot marketing site.

---

## 18. Tenant Resolution

Laravel:

```php
$host = strtolower(request()->getHost());
```

Lookup:

```php
$tenantDomain = TenantDomain::query()
    ->where('domain', $host)
    ->where('status', 'ACTIVE')
    ->first();
```

If found:

```php
$tenant = $tenantDomain->tenant;
```

Use the resolved tenant for the storefront request.

---

## 19. Unify Existing Storeboot Subdomains

Long term, resolve both Storeboot subdomains and custom domains through the same `tenant_domains` table.

Example:

```text
domain: business.storeboot.com
domain_type: STOREBOOT_SUBDOMAIN
status: ACTIVE
```

Then tenant resolution becomes a single hostname lookup instead of maintaining two completely separate routing systems.

---

## 20. Backward Compatibility

Existing Storeboot subdomains must continue working.

Recommended migration:

1. Create `tenant_domains`.
2. Backfill all existing tenant subdomains.
3. Preserve existing middleware temporarily.
4. Introduce unified domain resolution.
5. Remove old subdomain-only parsing only after verification.

---

## 21. SSL / HTTPS Requirements

Storeboot's wildcard certificate:

```text
*.storeboot.com
```

does not cover:

```text
www.mybakery.com
```

Each custom domain requires its own valid TLS certificate.

---

## 22. Initial SSL Implementation

For the first few customers, manual Let's Encrypt provisioning is acceptable.

Example:

```bash
sudo certbot --nginx -d www.mybakery.com
```

If both root and www are supported:

```bash
sudo certbot --nginx -d mybakery.com -d www.mybakery.com
```

Run only after DNS points to Storeboot.

---

## 23. SSL Status Tracking

Track:

```text
PENDING
PROVISIONING
ACTIVE
FAILED
```

Useful fields:

```text
ssl_status
ssl_issued_at
ssl_expires_at
failure_reason
```

---

## 24. Automatic SSL — Future Phase

Manual Certbot should not remain the long-term solution.

At scale, evaluate:

- automated Let's Encrypt certificate provisioning
- Caddy
- Traefik
- Cloudflare for SaaS / Custom Hostnames
- a dedicated certificate provisioning service

For the current Laravel + Ubuntu + Nginx + DigitalOcean stack, start with Nginx + Let's Encrypt and automate later.

---

## 25. Primary Domain

Each tenant should be able to select one primary storefront domain.

Example:

```text
sweetcrumbs.storeboot.com
is_primary = false
```

```text
www.sweetcrumbs.com
is_primary = true
```

The primary domain should be used for:

- storefront links
- product links
- QR codes
- customer-facing emails
- order links
- receipts
- SEO canonical URLs
- social share URLs

---

## 26. Redirect Non-Primary Domains

If a tenant has multiple active domains, redirect non-primary domains to the primary domain.

Example:

```text
sweetcrumbs.storeboot.com
301 -> https://www.sweetcrumbs.com
```

Use a permanent redirect only after the custom domain is active and stable.

---

## 27. Canonical Domain

For SEO, storefront pages should generate canonical URLs using the primary domain.

Example:

```html
<link rel="canonical" href="https://www.sweetcrumbs.com/products/chocolate-cake">
```

Avoid duplicate indexing across Storeboot and custom domains.

---

## 28. URL Generation

Avoid customer-facing links hardcoded to `storeboot.com`.

Create a helper/service such as:

```php
$tenant->primaryDomain();
```

or:

```php
TenantDomainService::primaryDomain($tenant);
```

Use this for:

- products
- categories
- cart
- order tracking
- online receipts
- QR codes
- WhatsApp share links
- storefront emails
- social metadata

---

## 29. Admin Domain Separation

Recommended:

```text
app.storeboot.com
```

for merchants and Storeboot internal users.

Use custom domains only for public storefronts in Phase 1.

Example:

```text
Customer: https://www.sweetcrumbs.com
Merchant: https://app.storeboot.com
```

---

## 30. Authentication Scope

Do not move Storeboot merchant/admin authentication onto customer domains in Phase 1.

Reasons include:

- cookie scope
- session handling
- CSRF
- CORS
- password-reset URLs
- OAuth callbacks
- multi-domain auth complexity

Custom domain support should initially cover:

```text
PUBLIC STOREFRONT ONLY
```

---

## 31. Host Header Security

Because Nginx may accept arbitrary hostnames, never blindly trust the Host header.

Every incoming storefront hostname must be validated against `tenant_domains`.

Invalid hosts must not influence:

- password-reset links
- email URLs
- redirect URLs
- generated links
- callback URLs
- canonical URLs

---

## 32. Reserved Domains

Prevent tenants from registering Storeboot-controlled domains.

Examples:

```text
storeboot.com
www.storeboot.com
app.storeboot.com
api.storeboot.com
admin.storeboot.com
custom.storeboot.com
```

Also reject localhost, private/local hostnames, and IP addresses.

---

## 33. Subscription / Pricing Rules

Recommended:

### Basic

```text
Custom Domain: No
```

### Business Growth

```text
Custom Domain: Yes
```

### Enterprise

```text
Custom Domain: Yes
Multiple Domains: Optional
```

---

## 34. Customer Workflow

```text
Settings
  -> Domain Settings
  -> Connect Your Domain
  -> Enter www.mybakery.com
  -> Storeboot Generates Verification Token
  -> Customer Adds DNS Records
  -> Customer Clicks Verify
  -> Storeboot Checks DNS
  -> Domain Verified
  -> SSL Provisioned
  -> Domain Activated
```

---

## 35. DNS Instructions UI

### Step 1 — Add CNAME

```text
Type: CNAME
Name: www
Target: custom.storeboot.com
```

### Step 2 — Verify Ownership

```text
Type: TXT
Name: _storeboot
Value: storeboot-verification=ABC123XYZ
```

### Step 3

```text
Verify Domain
```

---

## 36. DNS Verification Service

Create a backend service such as:

```text
DomainVerificationService
```

Responsibilities:

- normalize domain
- determine root domain
- build verification TXT hostname
- query DNS TXT records
- query CNAME/A records
- compare verification token
- update status
- set `last_dns_check_at`
- return meaningful errors

---

## 37. Suggested Verification Errors

```text
CNAME record not detected yet.
The domain currently points to another server.
Verification TXT record was not found.
Verification token does not match.
DNS changes may still be propagating.
This domain is already connected to another Storeboot account.
```

---

## 38. Domain Lifecycle

```text
PENDING
  -> VERIFYING
  -> VERIFIED
  -> ACTIVE
```

On error:

```text
VERIFYING -> FAILED
```

Allow retry.

---

## 39. Removing a Custom Domain

Before removal:

1. Confirm action.
2. Ensure another primary domain exists.
3. If removed domain is primary, switch primary back to Storeboot subdomain.
4. Disable routing.
5. Preserve audit history if needed.
6. Optionally revoke certificate later.

Consider soft deletes.

---

## 40. Changing Primary Domain

Add:

```text
Make Primary
```

When selected:

1. Set all tenant domains `is_primary = false`.
2. Set selected active domain `is_primary = true`.
3. Clear relevant caches.
4. Update canonical URL behavior.
5. Generate future storefront links using the new domain.

Only `ACTIVE` domains can become primary.

---

## 41. Root + WWW Handling

Treat:

```text
mybakery.com
www.mybakery.com
```

as separate hostnames.

Choose one canonical primary and redirect the other.

---

## 42. Middleware Recommendation

Create middleware such as:

```text
ResolveTenantFromDomain
```

Pseudo-code:

```php
public function handle($request, Closure $next)
{
    $host = strtolower($request->getHost());

    $tenantDomain = TenantDomain::query()
        ->with('tenant')
        ->where('domain', $host)
        ->where('status', 'ACTIVE')
        ->first();

    if (!$tenantDomain) {
        return response()->view('errors.domain-not-configured', [], 404);
    }

    app()->instance(CurrentTenant::class, $tenantDomain->tenant);

    return $next($request);
}
```

Adapt this to Storeboot's existing tenancy implementation.

---

## 43. Caching

Domain lookup will happen on every storefront request.

Cache:

```text
hostname -> tenant_id
```

Example:

```text
tenant_domain:www.mybakery.com
```

Invalidate when a domain is added, activated, removed, suspended, or made primary.

---

## 44. Domain Validation Model

Suggested Laravel validation:

```php
[
    'domain' => [
        'required',
        'string',
        'max:255',
        new ValidCustomDomain,
        Rule::unique('tenant_domains', 'domain'),
    ],
]
```

Use a dedicated validation rule for hostname rules and reserved domains.

---

## 45. Suggested Eloquent Model

```php
class TenantDomain extends Model
{
    protected $fillable = [
        'tenant_id',
        'domain',
        'domain_type',
        'status',
        'verification_token',
        'ssl_status',
        'is_primary',
        'verified_at',
        'ssl_issued_at',
        'ssl_expires_at',
        'activated_at',
        'last_dns_check_at',
        'failure_reason',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
        'ssl_issued_at' => 'datetime',
        'ssl_expires_at' => 'datetime',
        'activated_at' => 'datetime',
        'last_dns_check_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

Adapt names to the existing Storeboot models.

---

## 46. Suggested Tenant Relations

```php
public function domains()
{
    return $this->hasMany(TenantDomain::class);
}

public function primaryDomain()
{
    return $this->hasOne(TenantDomain::class)
        ->where('is_primary', true)
        ->where('status', 'ACTIVE');
}
```

---

## 47. Suggested Domain Service

Create:

```text
TenantDomainService
```

Responsibilities:

```text
addDomain()
normalizeDomain()
generateVerificationToken()
verifyDns()
activateDomain()
makePrimary()
removeDomain()
getPrimaryDomain()
buildStorefrontUrl()
```

Keep controllers thin.

---

## 48. Suggested Routes

```text
GET    /settings/domains
POST   /settings/domains
POST   /settings/domains/{domain}/verify
POST   /settings/domains/{domain}/make-primary
DELETE /settings/domains/{domain}
```

Or equivalent API routes if Storeboot uses an API-first frontend.

---

## 49. SSL Automation Interface

Even if SSL is manual initially, create an abstraction:

```text
SslProvisioningService
```

Method:

```php
provision(TenantDomain $domain)
```

The first implementation can mark SSL as pending and require an admin/manual action.

Later, automated SSL can replace the implementation without redesigning the domain module.

---

## 50. Super Admin Monitoring

Create a domain-management view showing:

```text
Tenant
Domain
Status
DNS Status
SSL Status
Primary?
Verified At
Activated At
Last DNS Check
Failure Reason
```

Useful filters:

```text
Pending
Verified
SSL Failed
Active
Failed
```

Useful actions:

```text
Recheck DNS
Retry SSL
Activate
Deactivate
View Tenant
```

---

## 51. Audit Logging

Log:

```text
CUSTOM_DOMAIN_ADDED
CUSTOM_DOMAIN_VERIFIED
CUSTOM_DOMAIN_SSL_STARTED
CUSTOM_DOMAIN_ACTIVATED
CUSTOM_DOMAIN_MADE_PRIMARY
CUSTOM_DOMAIN_REMOVED
CUSTOM_DOMAIN_FAILED
```

Store tenant, user, domain, event, metadata, and timestamp.

---

## 52. Storefront Routing

Custom domains should route only to storefront routes such as:

```text
/
/products
/products/{slug}
/categories/{slug}
/cart
/checkout
/order/{reference}
```

Admin routes should remain Storeboot-controlled.

---

## 53. URL Helper

Introduce a tenant-aware storefront URL helper.

```php
function storefront_url($tenant, string $path = ''): string
{
    $domain = $tenant->primaryDomain?->domain
        ?? $tenant->default_storeboot_domain;

    return 'https://' . $domain . '/' . ltrim($path, '/');
}
```

Avoid hardcoding storefront URLs elsewhere in the codebase.

---

## 54. Emails

Customer-facing emails should use the tenant primary domain.

Merchant/admin emails should continue linking to:

```text
app.storeboot.com
```

---

## 55. QR Codes

If Storeboot generates QR codes for storefronts, products, menus, tables, invoices, or checkout, use the tenant's primary domain.

Where possible, keep old active aliases redirecting to the primary domain so previously generated QR codes remain useful.

---

## 56. Testing Requirements

### Unit Tests

Test:

- normalization
- valid domain
- invalid domain
- reserved domain
- duplicate domain
- token generation
- primary-domain selection

### Feature Tests

Test:

- adding domain
- verification success
- verification failure
- activating domain
- resolving tenant from custom domain
- resolving tenant from Storeboot subdomain
- invalid host
- primary redirect
- removing domain
- subscription restriction

### Security Tests

Test:

- arbitrary Host header
- claiming Storeboot domains
- duplicate-domain takeover
- disabled tenant
- unverified domain
- URL generation from invalid host

---

## 57. Deployment Plan

### Phase 1 — Data Layer

- create `tenant_domains`
- add model and relationships
- backfill existing Storeboot subdomains
- add reserved-domain rules

### Phase 2 — Customer UI

- Domain Settings page
- add custom domain
- display DNS instructions
- verification token generation

### Phase 3 — DNS Verification

- TXT verification
- CNAME/A verification
- status updates
- retry logic

### Phase 4 — Tenant Routing

- implement unified domain resolver
- support custom domains
- preserve existing subdomains
- invalid-domain handling

### Phase 5 — SSL

Initially:

- manual Certbot
- admin activates after SSL success

Later:

- automated SSL provisioning

### Phase 6 — Primary Domain

- make primary
- redirects
- canonical URLs
- customer-facing URL generation

### Phase 7 — Automation

- queued DNS checks
- automatic SSL
- renewal monitoring
- failure alerts
- automated activation

---

## 58. First Customer Manual Workflow

Assume:

```text
www.sweetcrumbs.com
```

### Step 1 — Customer Enters Domain

Store:

```text
tenant_id: 92
domain: www.sweetcrumbs.com
domain_type: CUSTOM
status: PENDING
ssl_status: PENDING
```

Generate:

```text
storeboot-verification=FH73JK90ABCD
```

### Step 2 — Customer DNS

```text
Type: CNAME
Name: www
Target: custom.storeboot.com
```

and:

```text
Type: TXT
Name: _storeboot
Value: storeboot-verification=FH73JK90ABCD
```

### Step 3 — Storeboot Verification

Storeboot checks the CNAME/A destination and TXT token.

On success:

```text
status = VERIFIED
verified_at = now()
```

### Step 4 — SSL

```bash
sudo certbot --nginx -d www.sweetcrumbs.com
```

On success:

```text
ssl_status = ACTIVE
ssl_issued_at = now()
status = ACTIVE
activated_at = now()
```

### Step 5 — Primary Domain

Customer selects:

```text
Make Primary
```

Result:

```text
sweetcrumbs.storeboot.com -> is_primary = false
www.sweetcrumbs.com       -> is_primary = true
```

---

## 59. Expected Result

Visiting:

```text
https://www.sweetcrumbs.com
```

should:

1. reach Storeboot Nginx
2. reach Laravel
3. read Host `www.sweetcrumbs.com`
4. find the domain in `tenant_domains`
5. resolve the correct tenant
6. render that tenant's storefront
7. use the tenant branding
8. generate customer-facing links using the primary domain

---

## 60. Important Implementation Constraints

Do not:

- break existing `*.storeboot.com` tenants
- allow two tenants to claim the same domain
- activate a domain without ownership verification
- serve merchant/admin login on custom domains in Phase 1
- trust arbitrary Host headers
- assume wildcard Storeboot SSL covers custom domains
- hardcode storefront links to `storeboot.com`
- use unverified domains as canonical URLs
- expose another tenant when domain resolution fails

---

## 61. Recommended MVP

The first production-ready MVP should support:

```text
✓ Existing Storeboot subdomain
✓ One custom domain per eligible tenant
✓ CNAME-based custom domain
✓ TXT verification
✓ Manual SSL provisioning
✓ Laravel tenant resolution by hostname
✓ Primary-domain selection
✓ Optional redirect from Storeboot subdomain to custom domain
✓ Customer-facing storefront only
✓ Super-admin monitoring
```

Avoid overcomplicating the first release with automatic certificates or multiple custom aliases.

---

## 62. Future Enhancements

Later add:

- automatic SSL provisioning
- automatic certificate-renewal monitoring
- multiple custom domains
- root-domain onboarding automation
- Cloudflare for SaaS integration
- domain health monitoring
- automatic DNS polling
- custom-domain onboarding wizard
- automated canonical redirects
- branded error pages
- domain expiration monitoring
- ownership revalidation
- custom email sender domains
- custom customer portal domains

---

## 63. Suggested Customer Copy

### Custom Domain

Use your own domain for your Storeboot storefront.

Instead of:

```text
yourbusiness.storeboot.com
```

your customers can visit:

```text
www.yourbusiness.com
```

### How it works

1. Add your domain.
2. Update your DNS records.
3. Verify domain ownership.
4. Storeboot secures your domain with SSL.
5. Your Storeboot storefront becomes available on your domain.

---

## 64. Final Architectural Recommendation

For the first release, use:

```text
Storeboot Merchant Admin
app.storeboot.com

Storeboot Default Storefront
tenant.storeboot.com

Customer Custom Storefront
www.customer.com
```

All storefront domains resolve through:

```text
tenant_domains
```

The merchant admin remains centralized on:

```text
app.storeboot.com
```

This is the safest and cleanest path for Storeboot's current architecture while still giving customers professional branded storefront domains.

---

## 65. Codex Implementation Goal

Codex should implement this feature while preserving all existing tenant routing and subdomain behavior.

The implementation should be:

- backward compatible
- secure
- tenant-aware
- cache-friendly
- easy to automate later
- limited to storefront domains in the first release
- structured so SSL provisioning can be automated in a later phase

Before changing existing routing, tenancy middleware, Nginx assumptions, URL helpers, or storefront middleware, inspect the current Storeboot implementation and adapt this specification to the existing codebase instead of replacing working architecture unnecessarily.
