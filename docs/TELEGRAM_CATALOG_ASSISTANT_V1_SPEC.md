# Telegram Catalog Assistant — Version 1 Product Specification

**Status:** Proposed — approval required  
**Date:** October 21, 2026  
**Product:** Storeboot  
**Primary channel:** Telegram private chat  
**Initial scope:** Product creation, product lookup, price changes, cost-price changes, and storefront publication status

---

## 1. Executive summary

The Telegram Catalog Assistant allows an authorised Storeboot user to create and update
online-store products by sending ordinary Telegram messages and product photographs.
It is intended for merchants who find routine portal login and navigation stressful,
especially when they need to add a product or correct a price quickly from a phone.

The assistant is not an autonomous store manager. It interprets a user's message,
prepares a structured change, shows the proposed result, and requires an explicit
confirmation before Storeboot changes any product data.

Version 1 is intentionally narrow. It covers:

- connecting a Telegram user securely to a Storeboot user account;
- selecting the business being managed when a user belongs to multiple tenants;
- creating a simple product from one photograph and a caption;
- finding an existing product by SKU, barcode, exact name, or confirmed search result;
- changing selling price;
- changing cost price, including the existing inventory-revaluation decision;
- changing product status between Draft and Active;
- returning a link to the resulting product or storefront; and
- recording an audit trail for every requested and completed change.

Version 1 does not permit product deletion, bulk catalogue replacement, arbitrary
database operations, finance commands, order handling, or unrestricted inventory
adjustments.

---

## 2. Problem statement

Storeboot customers can manage their catalogue through the portal, but some merchants
primarily operate from messaging applications. Requiring them to log in, find the
catalogue page, open a product, edit fields, save, and then change status creates friction
for high-frequency tasks such as:

- adding a newly arrived product;
- publishing a product photograph;
- correcting a selling price;
- recording a new cost price; and
- moving a reviewed draft product online.

The proposed assistant reduces that workflow to a conversation without weakening
Storeboot's tenant isolation, role permissions, accounting rules, or auditability.

---

## 3. Product goals

Version 1 must:

1. Let a merchant complete common catalogue changes from Telegram.
2. Require only one secure account-linking exercise per Telegram account.
3. Make every proposed write understandable before it is executed.
4. Reuse Storeboot's existing product, inventory, accounting, permission, and image
   storage actions instead of duplicating business logic.
5. Prevent ambiguous product matches from changing the wrong record.
6. Prevent duplicate Telegram webhook delivery from creating duplicate products or
   applying a price change twice.
7. Preserve a complete record of who requested, confirmed, and executed every action.
8. Keep ongoing Telegram and AI costs measurable and controllable per tenant.

### 3.1 Success measures

The pilot is successful when:

- at least 90% of clearly written supported requests reach a correct confirmation
  preview without portal intervention;
- no action is applied to an unconfirmed or ambiguous product;
- no duplicate product or price update is caused by webhook retries;
- a merchant can create and publish a simple product in under two minutes;
- each completed bot action is attributable to a Storeboot user and tenant; and
- AI and messaging usage can be reported per tenant.

---

## 4. Non-goals for Version 1

The following are explicitly outside the first release:

- deleting products;
- changing categories, taxes, suppliers, badges, collections, or custom attributes on
  existing products;
- creating a complex option/variant structure from free-form text;
- bulk price changes such as "increase everything by 10%";
- spreadsheet imports through Telegram;
- general stock receiving, transfers, counts, write-offs, or corrections;
- sales, orders, refunds, payments, expenses, journals, payroll, or banking commands;
- customer-support conversations;
- Telegram group chats or channels;
- voice-note commands;
- scheduled autonomous catalogue changes;
- allowing the AI model to call the database or Storeboot actions directly; and
- a separate Telegram bot for every tenant.

These may be evaluated after the first release has demonstrated safe product matching,
permission enforcement, confirmation handling, and customer adoption.

---

## 5. Recommended operating model

Storeboot should operate one shared Telegram bot, provisionally named **Storeboot
Catalog Assistant**. Every Telegram user connects the bot to their own Storeboot user
account. The Storeboot account determines which tenants, branches, modules, and
permissions are available.

The bot must operate only in private chats in Version 1. Messages received from group
chats, channels, anonymous administrators, or unsupported Telegram contexts must not
perform catalogue actions.

### 5.1 Why the connection is user-based

Telegram must be linked to a Storeboot **user**, not directly to a tenant or phone
number, because:

- one owner may manage multiple Storeboot businesses;
- a staff member may have restricted catalogue permissions;
- a person may change phone numbers;
- phone numbers may be recycled;
- usernames may change; and
- tenant membership can be revoked without needing to reconfigure Telegram.

After linking, the stable Telegram user ID identifies the Storeboot user. The active
tenant is then selected from that user's current Storeboot memberships.

---

## 6. Account linking and tenant selection

### 6.1 Recommended primary linking flow

1. The user opens **Telegram Assistant** from Storeboot account settings.
2. Storeboot creates a signed, single-use connection token that expires after 10 minutes.
3. The user follows a Telegram deep link containing the token.
4. The bot receives `/start <token>` and sends the token to Storeboot's webhook.
5. Storeboot validates and consumes the token.
6. Storeboot records the Telegram user ID against the authenticated Storeboot user.
7. The bot displays the available businesses and asks the user to select one when more
   than one tenant is available.
8. Storeboot records the active tenant for the Telegram connection.

The token must be stored as a hash, must be single-use, and must not contain a raw user
ID, tenant ID, API key, or other sensitive information.

### 6.2 Phone-number fallback

If approval requires an onboarding flow that does not begin in the portal:

1. The bot displays Telegram's **Share phone number** button.
2. The flow is accepted only in a private chat.
3. The returned contact's Telegram user ID must match the message sender's Telegram
   user ID.
4. Storeboot normalises the number to an international format and looks for a matching
   user account.
5. Storeboot sends an OTP through an approved verification channel.
6. The Telegram connection is created only after correct OTP verification.

A phone number typed into a message, forwarded as a contact, or shared for another
person must never be sufficient to link an account.

### 6.3 Multiple tenants

If the linked user belongs to multiple active tenants, the bot shows a selection:

```text
Which store would you like to manage?

[Igwe Fashion]
[Mainland Supermarket]
```

The selection remains active until the user chooses **Switch store** or sends `/switch`.
Every confirmation preview must display the active store name.

### 6.4 Revocation

The connection can be revoked from:

- Storeboot account/security settings;
- the Telegram command `/disconnect`; or
- an administrator removing the user's tenant membership.

Revocation must invalidate pending commands immediately.

---

## 7. Supported Version 1 capabilities

### 7.1 Create a simple product from a photograph

The user sends one product photograph with an optional caption:

```text
Classic leather handbag. Sell 35,000. Cost 22,000. Publish it.
```

The assistant extracts a proposed:

- product name;
- short description;
- existing or suggested category;
- selling price;
- cost price;
- main image;
- inventory tracking setting; and
- requested status.

It then sends a preview:

```text
Igwe Fashion

Create product?

Classic Leather Handbag
Selling price: ₦35,000.00
Cost price: ₦22,000.00
Opening inventory: 1
Status: Active

Nothing has been changed yet.

[Create & publish] [Save as draft]
[Cancel]
```

#### Product-creation rules

- Version 1 creates simple products only.
- One Telegram photograph becomes the product's main image.
- The uploaded original filename is not used as the stored filename.
- Every created product receives a unique slug and SKU using existing Storeboot logic.
- An existing product is never updated merely because a photograph, inferred name, or
  original filename resembles an existing product.
- If no explicit price is provided, selling price and cost price are both zero.
- A photo-only import is saved as Draft.
- A product with a zero selling price cannot be published from Telegram.
- When the Inventory module and a valid tenant inventory location are available, a
  photo-only import follows the current provisional-opening rule of one inventory unit
  at zero cost.
- When the first real cost replaces the default zero cost, existing provisional stock is
  revalued automatically under Storeboot's current rules.
- AI-generated content is a suggestion shown in the preview, not authoritative data.
- The user can choose **Edit details** to answer targeted questions before confirming.

### 7.2 Find a product

The assistant supports lookup by:

1. exact variant SKU;
2. exact barcode;
3. exact product name;
4. exact variant name;
5. close name search; and
6. a selection from recent bot-created products.

SKU and barcode matches are preferred. A close name match is never sufficient to write
to a product without user selection.

When several records match, the bot displays no more than five at once, including image,
name, SKU, current price, and status:

```text
I found more than one product called Black Office Chair.
Choose the correct one:

[Black Office Chair · CHR-100 · ₦110,000]
[Black Office Chair XL · CHR-104 · ₦145,000]
[Cancel]
```

### 7.3 Change selling price

Example request:

```text
Change CHR-100 to 125k.
```

Confirmation preview:

```text
Igwe Furniture

Change selling price?

Black Office Chair · CHR-100
Current price: ₦110,000.00
New price: ₦125,000.00

[Confirm price change] [Cancel]
```

Rules:

- `catalog.update` permission is required.
- The product or variant must be resolved unambiguously.
- Money is normalised using the tenant currency and stored in minor units.
- Values such as `25k`, `₦25,000`, and `25000` may be interpreted as ₦25,000 for an NGN
  tenant, but the normalised amount must be shown before confirmation.
- Negative prices are rejected.
- Zero selling price is allowed only when the resulting product remains Draft.
- A price update does not change inventory quantity, cost, category, status, or image.

### 7.4 Change cost price

Example request:

```text
The cost of CHR-100 is now 78,000.
```

The bot must require permission to update the catalogue and permission to view product
costs. It must not expose current cost or margin to a user who lacks cost visibility.

If the current cost is non-zero and stock exists:

```text
Black Office Chair · CHR-100
Cost price: ₦70,000.00 → ₦78,000.00
Current stock: 5

Should Storeboot revalue the existing inventory units?

[Yes, revalue inventory]
[No, future stock only]
[Cancel]
```

The selected response sets the same hidden revaluation instruction used by the portal.
Quantity does not change. Storeboot's existing average-cost, inventory-movement, and
accounting logic performs any confirmed revaluation.

If the existing cost is zero and the new cost is positive, Storeboot automatically
revalues the applicable zero-cost inventory without asking a redundant question.

### 7.5 Change publication status

Supported status commands are limited to Draft and Active:

```text
Publish CHR-100.
Move CHR-100 back to draft.
```

Rules:

- `catalog.update` permission is required.
- The product must be resolved unambiguously.
- Publishing requires a positive selling price.
- The bot shows current and proposed status before execution.
- Archived or otherwise unsupported status transitions must be completed in the portal.

### 7.6 Show product details

Read-only requests may return:

- name and main image;
- SKU or barcode;
- selling price;
- status;
- storefront URL when active; and
- cost price only when the user has `catalog.costs.view`.

Read-only responses do not require a confirmation button.

---

## 8. Intent and confirmation policy

Every incoming request is converted to a strict, versioned intent. Version 1 supports:

```text
catalog.product.create
catalog.product.lookup
catalog.price.update
catalog.cost.update
catalog.status.update
connection.switch_tenant
connection.disconnect
```

The AI may propose only these intents. Unknown or unsupported requests receive an
explanation and a link to the appropriate Storeboot portal page.

### 8.1 Confirmation matrix

| Operation | Confirmation required | Notes |
|---|---:|---|
| Product lookup | No | Read-only |
| Show selling price/status | No | Read-only |
| Show cost price | No | Requires cost-view permission |
| Create Draft product | Yes | Preview all known fields |
| Create and publish | Yes | Positive selling price required |
| Change selling price | Yes | Show old and new values |
| Change cost price | Yes | May require second revaluation decision |
| Change Draft/Active status | Yes | Show current and proposed status |
| Switch active tenant | Yes | Display destination tenant |
| Disconnect account | Yes | Invalidates pending commands |

No catalogue mutation is executed from the initial free-form message.

### 8.2 Pending-command lifetime

- A confirmation preview expires after 10 minutes.
- Confirmation buttons are single-use.
- A command is bound to the Telegram user, private chat, Storeboot user, tenant, and
  exact proposed payload.
- Editing the original Telegram message does not alter an existing pending command.
- After expiry, the user must submit the request again.

---

## 9. Permissions and tenant isolation

The bot is an additional Storeboot interface, not an alternative authorization system.
It must evaluate current permissions immediately before preparing a preview and again
inside the execution transaction.

| Bot capability | Required Storeboot permission |
|---|---|
| Find/view products | `catalog.view` |
| Create product | `catalog.create` |
| Change selling price | `catalog.update` |
| Change product status | `catalog.update` |
| View or change cost price | `catalog.update` and `catalog.costs.view` |
| Apply inventory revaluation | Existing catalogue update plus inventory/accounting rules |

Additional requirements:

- The selected tenant must be active and currently accessible to the linked user.
- Product and variant queries must always include `tenant_id`.
- Route model binding alone is not a tenant-security boundary.
- A revoked membership or permission takes effect immediately, including against an
  already prepared but unconfirmed command.
- The bot must never reveal another tenant's product names, images, prices, SKUs, or
  search matches.

---

## 10. Conversation behaviour

### 10.1 Supported input styles

The bot accepts:

- natural-language messages;
- explicit commands;
- one photograph with a caption; and
- button responses generated by the bot.

Examples:

```text
/price CHR-100 125000
/cost CHR-100 78000
/find black office chair
/publish CHR-100
/draft CHR-100
/switch
/help
/disconnect
```

Explicit commands should bypass AI interpretation when their structure is valid. This
reduces cost and removes unnecessary ambiguity.

### 10.2 Clarification

The assistant asks one focused question at a time when required data is missing:

```text
What selling price should I use for Classic Leather Handbag?
```

It must not silently invent a price, cost, SKU, quantity, tax, or publication status.

### 10.3 Completion response

After a successful action, the bot returns:

- what changed;
- the final price/status;
- the acting store;
- the Storeboot product ID or SKU;
- the product's admin link; and
- the storefront link when the product is active.

Example:

```text
Product published successfully.

Classic Leather Handbag · CLH-204
Selling price: ₦35,000.00
Store: Igwe Fashion

[View product] [Open storefront]
```

---

## 11. Technical architecture

```text
Telegram private message/photo
            |
            v
Signed Storeboot Telegram webhook
            |
            v
Update deduplication and linked-user resolution
            |
            v
Tenant selection + current permission checks
            |
            v
Command parser
  | explicit command -> deterministic parser
  | natural language/photo -> constrained AI extraction
            |
            v
Product resolver and business-rule validator
            |
            v
Persist pending command + send confirmation keyboard
            |
            v
User confirms callback
            |
            v
Recheck identity, tenant, permission, expiry and current data
            |
            v
Existing Storeboot action inside database transaction
            |
            v
Audit result + Telegram completion response
```

### 11.1 Reuse of existing Storeboot logic

The integration must call application actions rather than simulate browser requests.
Relevant existing behaviour includes:

- product photo interpretation;
- unique product slug and SKU generation;
- product image storage;
- draft photo imports with zero selling and cost price;
- provisional opening inventory;
- product status changes;
- selling-price and cost-price persistence;
- inventory cost revaluation;
- average-cost updates;
- inventory movements; and
- accounting journal posting.

If an existing action is too tightly coupled to an HTTP controller, it should be
extracted into a channel-neutral domain action that both the portal and Telegram use.

### 11.2 Proposed application components

```text
Modules/TelegramCatalog/
├── Actions/
│   ├── LinkTelegramUserAction
│   ├── ResolveTelegramTenantAction
│   ├── InterpretCatalogCommandAction
│   ├── ResolveCatalogProductAction
│   ├── PrepareTelegramCommandAction
│   ├── ConfirmTelegramCommandAction
│   └── SendTelegramMessageAction
├── Http/Controllers/
│   └── TelegramWebhookController
├── Jobs/
│   ├── ProcessTelegramUpdateJob
│   └── SendTelegramReplyJob
├── Models/
│   ├── TelegramConnection
│   ├── TelegramCommand
│   └── TelegramUpdate
├── Support/
│   ├── TelegramApiClient
│   ├── TelegramCommandSchema
│   └── TelegramMessageFormatter
└── routes/
    └── webhook.php
```

The final module name can change during implementation, but ownership should remain
separate from `Catalog` so Telegram transport concerns do not leak into catalogue domain
logic.

---

## 12. Proposed data model

All identifiers and tokens must use appropriate indexes, and all sensitive token values
must be encrypted or hashed as described below.

### 12.1 `telegram_connections`

```text
id
telegram_user_id              unique bigint/string
telegram_private_chat_id      bigint/string
user_id                       FK users
active_tenant_id              nullable FK tenants
phone_number_encrypted        nullable
phone_verified_at             nullable timestamp
linked_at                     timestamp
last_used_at                  nullable timestamp
revoked_at                    nullable timestamp
created_at
updated_at
```

The Telegram user ID is the durable external identity. Phone number is optional and is
not queried for every message.

### 12.2 `telegram_link_tokens`

```text
id
user_id                       FK users
token_hash                    unique
expires_at
consumed_at                   nullable
created_at
```

Raw link tokens are never stored.

### 12.3 `telegram_updates`

```text
id
telegram_update_id            unique
telegram_user_id
telegram_chat_id
update_type
payload_fingerprint
processing_status             received | processing | completed | failed | ignored
received_at
processed_at                  nullable
failure_code                  nullable
created_at
updated_at
```

The unique Telegram update ID is the first idempotency boundary.

### 12.4 `telegram_commands`

```text
id                            UUID recommended
telegram_update_id            FK telegram_updates
telegram_connection_id        FK telegram_connections
user_id                       FK users
tenant_id                     FK tenants
intent                        versioned string
target_type                   nullable
target_id                     nullable
proposed_payload              encrypted JSON where necessary
original_snapshot             nullable encrypted JSON
status                        pending | confirmed | executing | completed |
                              cancelled | expired | rejected | failed
confirmation_nonce_hash       unique
expires_at
confirmed_at                  nullable
executed_at                   nullable
result_type                   nullable
result_id                     nullable
error_code                    nullable
created_at
updated_at
```

The command record is the audit and second idempotency boundary. A completed command
cannot execute again.

---

## 13. AI boundary and structured output

AI is used only to transform natural-language/photo input into a proposed structured
intent. It does not receive database credentials, execute SQL, invoke arbitrary URLs, or
choose a tenant.

Example extraction schema:

```json
{
  "schema_version": "1.0",
  "intent": "catalog.price.update",
  "product_reference": {
    "type": "sku",
    "value": "CHR-100"
  },
  "selling_price_minor": 12500000,
  "currency_code": "NGN",
  "confidence": 0.98,
  "missing_fields": []
}
```

Rules:

- Output must satisfy a strict JSON schema.
- Unknown keys are rejected.
- Tenant ID, Storeboot user ID, permissions, product ID, current prices, and execution
  options are always supplied or resolved by Storeboot, never trusted from AI output.
- The AI confidence score never replaces confirmation.
- Low-confidence or contradictory input triggers clarification.
- Product matching is deterministic after extraction.
- The final confirmation is generated from validated Storeboot data, not directly from
  AI prose.

---

## 14. Telegram webhook and API security

### 14.1 Inbound webhook

- Use HTTPS only.
- Configure Telegram's webhook secret token.
- Validate the `X-Telegram-Bot-Api-Secret-Token` header using constant-time comparison.
- Accept only supported update types.
- Persist and acknowledge a valid update quickly, then process it through the queue.
- Enforce request-size limits.
- Rate-limit repeated invalid or unlinked requests.
- Never place the bot token in a URL visible to logs or customers.

### 14.2 Outbound API

- Store the bot token in deployment secrets, not the database or repository.
- Redact tokens, connection codes, OTPs, and Telegram API URLs from logs.
- Use bounded HTTP timeouts and retries.
- Retry safe message sends, but do not retry catalogue execution without an idempotency
  check.

### 14.3 Required configuration

```text
TELEGRAM_CATALOG_ENABLED=false
TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=
TELEGRAM_WEBHOOK_SECRET=
TELEGRAM_COMMAND_TTL_MINUTES=10
TELEGRAM_MONTHLY_AI_LIMIT_PER_TENANT=
```

The feature should default to disabled until configured and explicitly enabled for pilot
tenants.

---

## 15. Idempotency and concurrency

Telegram may deliver the same update more than once. Users may also press a button
multiple times or change a product in the portal while a Telegram preview is pending.

Version 1 must therefore:

- uniquely store Telegram `update_id`;
- use a single-use confirmation nonce;
- lock the pending command during confirmation;
- transition command status atomically;
- take a snapshot of the target product's relevant fields;
- re-read and lock the product/variant immediately before execution;
- reject or rebuild the preview if the price, cost, status, tenant access, or permission
  changed after preparation; and
- use existing idempotent accounting and inventory source references.

Example stale-preview response:

```text
This product changed after your preview was prepared.
Current selling price is now ₦118,000. Nothing was changed.

[Prepare a new preview]
```

---

## 16. Audit, privacy, and retention

Every command record must include:

- Telegram update and user identifiers;
- linked Storeboot user;
- tenant;
- intent;
- target product/variant where applicable;
- old and new values;
- confirmation time;
- execution result; and
- failure or rejection reason.

Security audit events should be emitted for:

- account linked;
- link failed;
- active tenant changed;
- permission denied;
- command confirmed;
- catalogue action completed;
- suspicious repeated attempts; and
- account disconnected.

Privacy rules:

- Store only the minimum Telegram profile data required for the connection.
- Do not store full free-form message bodies indefinitely.
- Product photographs retained by Storeboot follow normal product-image retention.
- Temporary downloaded Telegram files must be removed after processing.
- Phone numbers must be encrypted at rest and may be discarded after verification if
  not operationally required.
- Users must be able to disconnect Telegram and remove the connection.

A final retention period for raw Telegram updates must be approved before launch. The
recommended default is 30 days for operational payloads and longer retention only for
the structured command/audit record.

---

## 17. Error and recovery behaviour

The assistant must use clear, actionable responses.

| Condition | Required response |
|---|---|
| User is not linked | Offer secure connection instructions |
| No accessible tenant | Explain that Storeboot access is required |
| Multiple tenants, none selected | Ask the user to select a store |
| Permission denied | State which action is unavailable; reveal no protected data |
| No matching product | Ask for SKU/barcode or offer a portal search link |
| Multiple matches | Show a selection; do not guess |
| Unsupported variant operation | Explain the Version 1 limit and link to the portal |
| Invalid/negative price | Ask for a valid non-negative amount |
| Publish with zero price | Keep Draft and request a selling price |
| Confirmation expired | Apply nothing and offer to rebuild the preview |
| Product changed since preview | Apply nothing and prepare a fresh preview |
| AI unavailable | Offer explicit commands or save nothing |
| Telegram API unavailable after execution | Preserve completion record and retry only the reply |
| Storeboot execution fails | Roll back the transaction and report that nothing changed |

The user must never receive a success message until Storeboot has committed the action.

---

## 18. Cost controls

Standard Telegram bot messaging is available at no cost within Telegram's normal rate
limits. Version 1 does not require paid broadcasts.

The variable cost is primarily AI use for natural language and photographs. Cost should
be controlled by:

- using deterministic parsing for explicit commands;
- using a compact vision/text model for supported intents;
- resizing product images before AI processing;
- sending one structured extraction request rather than a multi-agent conversation;
- avoiding a second AI call for confirmation or execution;
- recording model, input usage, output usage, and estimated cost per command;
- enforcing per-tenant monthly AI limits; and
- disabling photo interpretation gracefully when a limit is reached while retaining
  explicit price commands.

Low-volume pilots should run on the existing Storeboot application and database queue.
Additional workers or dedicated queue infrastructure should be introduced only when
measured throughput requires them.

The commercial model may be decided separately. Options include:

- include a monthly allowance in eligible Storeboot plans;
- sell the assistant as a subscription add-on; or
- include text commands and charge only for AI photo credits.

---

## 19. Monitoring and operational controls

The platform administration view should show:

- connection count;
- active connections in the last 30 days;
- commands by intent and tenant;
- completed, cancelled, rejected, expired, and failed commands;
- AI requests and estimated cost;
- average processing time;
- webhook failures and retry count;
- duplicate updates ignored;
- ambiguous-product rate; and
- permission-denied attempts.

Operational controls must include:

- global kill switch;
- per-tenant enable/disable control;
- per-connection revoke action;
- AI monthly limit;
- webhook health check;
- bot-token rotation procedure; and
- replay-safe failed-job handling.

---

## 20. Testing requirements

### 20.1 Account and tenant tests

- Valid single-use link connects the correct Telegram and Storeboot users.
- Expired, reused, altered, or cross-user tokens fail.
- Contact linking requires a matching Telegram contact user ID and completed OTP.
- A multi-tenant user must select an active tenant.
- Tenant switching cannot select an inaccessible tenant.
- Revocation invalidates pending commands.

### 20.2 Permission and isolation tests

- `catalog.view`, `catalog.create`, `catalog.update`, and `catalog.costs.view` are enforced
  independently.
- A user cannot discover or alter another tenant's product.
- Removing membership after preview prevents confirmation.
- Removing permission after preview prevents confirmation.
- Cost values are never shown without cost visibility permission.

### 20.3 Product tests

- Photo plus valid caption prepares the correct product preview.
- Photo-only creation defaults to Draft, zero prices, and provisional inventory rules.
- Same original filename never overwrites an existing image or product.
- Duplicate webhook delivery creates exactly one product.
- Zero selling price cannot be published.
- Unsupported variant creation is rejected safely.

### 20.4 Price and cost tests

- Exact SKU/barcode lookup resolves the correct tenant product.
- Ambiguous names require selection.
- Selling-price confirmation changes only the selling price.
- Cost-price confirmation follows the selected revaluation instruction.
- First change from zero cost automatically revalues applicable provisional inventory.
- Revaluation preserves quantity and posts one movement/journal outcome.
- Repeated confirmation cannot apply twice.
- A stale preview cannot overwrite a newer portal change.

### 20.5 Webhook and resilience tests

- Invalid webhook secret is rejected.
- Valid webhook returns promptly and queues work.
- Unsupported chat/update types are ignored safely.
- Telegram reply failure after successful execution does not rerun execution.
- AI timeout does not create or modify a product.
- Database failure rolls back the catalogue operation.

---

## 21. Acceptance criteria for approval and launch

Version 1 is ready for a controlled pilot only when all of the following are true:

1. A Telegram account can be linked and revoked securely.
2. A linked user's tenant and current permissions are enforced for every operation.
3. One photograph can prepare and confirm a simple Draft product.
4. A caption with valid prices can prepare a product preview without silently publishing.
5. An Active product requires a positive selling price and explicit publication
   confirmation.
6. Selling price can be changed only after an old-versus-new preview.
7. Cost price uses the existing inventory-revaluation rules.
8. Ambiguous product names cannot cause a write.
9. Telegram retries and repeated button presses cannot duplicate a write.
10. Portal changes made after preview cannot be silently overwritten.
11. Every completed action has an attributable command and security audit record.
12. AI usage and estimated cost are measurable per tenant.
13. The feature can be disabled globally or for one tenant without deployment.
14. All security, tenant-isolation, catalogue, revaluation, and webhook regression tests
    pass.
15. A pilot runbook, token-rotation procedure, and support escalation path are available.

---

## 22. Rollout plan

### Phase A — Internal sandbox

- Configure a non-production Telegram bot.
- Enable only internal Storeboot tenant accounts.
- Validate linking, message parsing, confirmations, retry behaviour, and audit logs.
- Use test products and test storefronts.

### Phase B — Limited merchant pilot

- Select 3–5 merchants who frequently create or reprice products.
- Enable one owner or trusted catalogue manager per tenant.
- Apply conservative AI and command limits.
- Review all failed and ambiguous commands daily.
- Collect time-to-completion and customer feedback.

### Phase C — Controlled availability

- Make the feature available to eligible subscriptions or as an add-on.
- Add self-service linking and connection management.
- Retain per-tenant feature flags and usage limits.
- Expand only after the pilot meets the success and safety measures.

---

## 23. Approval decisions requested

Approval is requested for the following Version 1 decisions:

| Decision | Recommendation |
|---|---|
| Bot ownership | One Storeboot-operated bot shared across tenants |
| Chat scope | Private chats only |
| Identity | Link Telegram user ID to Storeboot user |
| Primary onboarding | Signed single-use Storeboot-to-Telegram link |
| Phone onboarding | Optional fallback with Telegram contact validation and OTP |
| Tenant handling | User selects an active tenant; `/switch` changes it |
| Write policy | Explicit confirmation for every catalogue mutation |
| Product creation | Simple products, one main photo, Draft by default |
| Missing price | Selling and cost price remain zero; no inferred price |
| Opening inventory | Reuse provisional quantity-one import rule when applicable |
| Publishing | Positive selling price plus explicit confirmation |
| Product matching | SKU/barcode preferred; ambiguous names require selection |
| Cost changes | Reuse existing revaluation and accounting behaviour |
| Variants | Existing exact-SKU variant repricing only; no variant construction |
| Excluded actions | No delete, bulk update, general inventory, sales, or finance commands |
| AI approach | Compact constrained model; deterministic explicit commands |
| Pilot | Internal sandbox followed by 3–5 selected merchants |

Approval may be given for the full table or returned with changes to individual
decisions. Implementation should not begin until the identity method, confirmation
policy, permission requirements, product defaults, cost limits, and pilot group are
approved.

---

## 24. Potential later versions

After Version 1 is proven safe and useful, possible additions include:

- multiple images per product;
- guided variant creation;
- batch photo imports;
- controlled stock receipt and adjustment workflows;
- approval routing for sensitive changes;
- voice-note transcription;
- scheduled catalogue tasks;
- low-stock and order notifications;
- sales summaries; and
- WhatsApp or other messaging channels using the same channel-neutral command layer.

These are not implied by approval of Version 1.

---

## 25. External references

- [Telegram Bot API](https://core.telegram.org/bots/api)
- [Telegram inline keyboards](https://core.telegram.org/bots/features#inline-keyboards)
- [Telegram webhook configuration](https://core.telegram.org/bots/api#setwebhook)
- [Telegram file retrieval](https://core.telegram.org/bots/api#getfile)
- [Telegram contact-request button](https://core.telegram.org/bots/api#keyboardbutton)
- [OpenAI API pricing](https://developers.openai.com/api/docs/pricing)

