# Endpoint Test Plan

Auth types:
- **Public** — no auth
- **Sanctum** — `auth:sanctum` bearer token (logged-in user)
- **API Key** — `api.key` middleware (external app key)
- **JWT (signed)** — verified via JWT signature, no user/session

---

## Auth (`AuthController`)

### POST `/register` — Public
```json
{
  "name": "string, required, max:255",
  "email": "string, required, email, max:255, unique:users",
  "password": "string, required, confirmed, min:8",
  "password_confirmation": "string, required (must match password)",
  "tenant_name": "string, optional, nullable, max:255"
}
```
Test cases: valid registration creates user+tenant+token; duplicate email rejected; missing/invalid fields rejected; password confirmation mismatch rejected; password hashed not stored plain; transaction rollback on tenant creation failure leaves no orphan user.

### POST `/login` — Public
```json
{
  "email": "string, required, email",
  "password": "string, required"
}
```
Test cases: valid credentials return token; wrong password rejected (401); unknown email rejected.

### POST `/auth/clerk` — Public
```json
{
  "session_token": "string, required"
}
```
Test cases: valid Clerk JWT creates/links user + tenant, returns token; missing `session_token` → 422; invalid/expired/tampered JWT rejected; missing email claim rejected; Clerk not configured → 501; existing user (login) vs new user (register) path.

### POST `/logout` — Sanctum
No body.
Test cases: valid token revoked, subsequent requests 401; no token → 401.

## User (`UserController`)

### GET `/user` — Sanctum
No body. Returns authenticated user; no token → 401.

### GET `/me` — Sanctum
No body. Returns current user + active tenant context.

### PATCH `/me` — Sanctum
```json
{
  "name": "string, optional, max:255",
  "email": "string, optional, email, max:255, unique:users (ignoring self)",
  "password": "string, optional, confirmed, min:8",
  "password_confirmation": "string, required if password present"
}
```
Test cases: valid partial update persists; email uniqueness ignores own record; invalid fields rejected.

### POST `/me/switch-tenant` — Sanctum
```json
{
  "tenant_id": "integer, required, exists:tenants,id"
}
```
Test cases: switch to tenant user belongs to succeeds; switch to tenant user does NOT belong to → 403; invalid/non-existent tenant id → 422.

## Tenants (`TenantsController`) — apiResource except store

### GET `/tenants` — Sanctum
No body. Lists only tenants user has access to.

### GET `/tenants/{id}` — Sanctum
No body. Owner/member can view; non-member → 403/404.

### PATCH/PUT `/tenants/{id}` — Sanctum
```json
{
  "name": "string, optional, max:255",
  "slug": "string, optional, max:255, unique:tenants (ignoring self)",
  "semoa_api_key": "string, optional, nullable",
  "semoa_merchant_id": "string, optional, nullable",
  "status": "string, optional, in:active,inactive,suspended",
  "settings": "array, optional, nullable"
}
```
Test cases: authorized role can update; unauthorized role → 403; slug uniqueness ignores self; invalid status value rejected.

### DELETE `/tenants/{id}` — Sanctum
No body. Authorized role can delete; unauthorized → 403; cascading effects (subscriptions, invitations) handled.

## Tenant Invitations (`TenantInvitationsController`)

### GET `/tenant-invitations` — Sanctum
No body. Lists invitations scoped to tenant.

### POST `/tenant-invitations` — Sanctum
```json
{
  "email": "string, required, email, max:255 (cannot equal inviter's own email)",
  "role": "string, optional, max:255",
  "expires_at": "date, optional"
}
```
Test cases: valid invite created + notification sent; inviting self rejected; duplicate invite to same email; inviting existing tenant member.

### GET `/tenant-invitations/{id}` — Sanctum
No body. Scoped access only.

### DELETE `/tenant-invitations/{id}` — Sanctum
No body. Authorized cancel; unauthorized → 403.

### POST `/tenant-invitations/{token}/accept` — Sanctum
No body (token in URL). Valid token accepted → transaction creates membership; expired token rejected; already-accepted token rejected; token for different user rejected.

### POST `/tenant-invitations/{token}/decline` — Sanctum
No body (token in URL). Valid decline marks invitation declined; expired/invalid token rejected.

## Applications (`ApplicationsController`) — full apiResource

### GET `/applications` — Sanctum
No body. Tenant-scoped list.

### POST `/applications` — Sanctum
```json
{
  "name": "string, required, max:255",
  "slug": "string, required, max:255, unique:applications",
  "status": "string, optional, in:active,inactive",
  "webhook_url": "string, optional, nullable, url",
  "webhook_secret": "string, optional, nullable"
}
```
Test cases: valid create; missing required fields rejected; duplicate slug rejected; invalid webhook_url format rejected.

### GET `/applications/{id}` — Sanctum
No body. Scoped access; cross-tenant access → 403/404.

### PATCH/PUT `/applications/{id}` — Sanctum
```json
{
  "name": "string, optional, max:255",
  "slug": "string, optional, max:255, unique:applications (ignoring self)",
  "status": "string, optional, in:active,inactive",
  "webhook_url": "string, optional, nullable, url",
  "webhook_secret": "string, optional, nullable"
}
```
Test cases: valid partial update; unauthorized tenant → 403; slug uniqueness ignores self.

### DELETE `/applications/{id}` — Sanctum
No body. Valid delete; cascading feature/plan links handled; unauthorized → 403.

## Features (`FeaturesController`) — full apiResource

### GET `/features` — Sanctum
No body. Tenant/application-scoped list.

### POST `/features` — Sanctum
```json
{
  "application_id": "integer, required, exists:applications,id",
  "code": "string, required, max:255, unique:features",
  "description": "string, required"
}
```
Test cases: valid create; duplicate code rejected; invalid application_id rejected.

### GET `/features/{id}` — Sanctum
No body. Scoped access.

### PATCH/PUT `/features/{id}` — Sanctum
```json
{
  "application_id": "integer, optional, exists:applications,id",
  "code": "string, optional, max:255, unique:features (ignoring self)",
  "description": "string, optional"
}
```
Test cases: valid update; unauthorized → 403; code uniqueness ignores self.

### DELETE `/features/{id}` — Sanctum
No body. Valid delete; still-attached-to-plan case handled.

## Plans (`PlansController`) — full apiResource + attach/detach

### GET `/plans` — Sanctum
No body. Tenant-scoped list.

### POST `/plans` — Sanctum
```json
{
  "application_id": "integer, required, exists:applications,id",
  "name": "string, required, max:255",
  "price": "numeric, required, min:0",
  "currency": "string, required, max:10",
  "duration_days": "integer, required, min:1",
  "grace_period_days": "integer, optional, min:0",
  "is_active": "boolean, optional"
}
```
Test cases: valid create; negative price/duration rejected; missing required fields rejected.

### GET `/plans/{id}` — Sanctum
No body. Scoped access.

### PATCH/PUT `/plans/{id}` — Sanctum
```json
{
  "application_id": "integer, optional, exists:applications,id",
  "name": "string, optional, max:255",
  "price": "numeric, optional, min:0",
  "currency": "string, optional, max:10",
  "duration_days": "integer, optional, min:1",
  "grace_period_days": "integer, optional, min:0",
  "is_active": "boolean, optional"
}
```
Test cases: valid update; unauthorized → 403.

### DELETE `/plans/{id}` — Sanctum
No body. Delete succeeds; plan with active subscriptions handled (block or cascade).

### POST `/plans/{plan}/features` — Sanctum
```json
{
  "feature_id": "integer, required, exists:features,id",
  "limit": "integer, optional, nullable, min:0"
}
```
Test cases: attach valid feature; attach already-attached feature (idempotent/error); attach feature from another tenant → 403.

### DELETE `/plans/{plan}/features/{feature}` — Sanctum
No body. Detach succeeds; detach non-attached feature → 404/no-op.

## API Keys (`ApiKeysController`) — apiResource except create/edit

### GET `/api-keys` — Sanctum
No body. Tenant-scoped list; key secret not exposed in list.

### POST `/api-keys` — Sanctum
```json
{
  "application_id": "integer, required, exists:applications,id",
  "name": "string, required, max:255",
  "environment": "string, required, in:live,test"
}
```
Test cases: valid create returns plaintext key once; missing fields rejected; invalid environment value rejected.

### GET `/api-keys/{id}` — Sanctum
No body. Scoped access; plaintext key not re-exposed.

### PATCH/PUT `/api-keys/{id}` — Sanctum
```json
{
  "name": "string, optional, max:255",
  "environment": "string, optional, in:live,test"
}
```
Test cases: valid update; unauthorized → 403.

### DELETE `/api-keys/{id}` — Sanctum
No body. Delete revokes key; subsequent use of revoked key on public endpoints fails.

## Subscribers (`SubscribersController`)

### GET `/subscribers` — Sanctum
No body. Tenant-scoped list; pagination/filtering if present.

### GET `/subscribers/{id}` — Sanctum
No body. Scoped access; cross-tenant → 403/404.

### PATCH `/subscribers/{id}` — Sanctum
```json
{
  "tenant_id": "integer, optional, exists:tenants,id",
  "external_id": "string, optional, max:255, unique:subscribers (ignoring self)",
  "phone_number": "string, optional, max:30",
  "email": "string, optional, nullable, email, max:255",
  "environment": "string, optional, in:live,test"
}
```
Note: `authorize()` returns `false` in `UpdateSubscribersRequest` — confirm route actually reaches controller / policy grants access, otherwise every call 403s by design.
Test cases: valid update; unauthorized field changes rejected; unauthorized tenant → 403; external_id uniqueness ignores self.

## Subscriptions (`SubscriptionsController`)

### GET `/subscriptions` — Sanctum
No body. Tenant-scoped list; filter by status/plan if supported.

### GET `/subscriptions/{id}` — Sanctum
No body. Scoped access.

### DELETE `/subscriptions/{id}` — Sanctum
No body. Cancels subscription; already-cancelled → error/no-op; unauthorized → 403.

## Transactions (`TransactionsController`)

### GET `/transactions` — Sanctum
No body. Tenant-scoped list.

### GET `/transactions/summary` — Sanctum
No body. Aggregate totals correct (sum/count by status/period).

### GET `/transactions/{id}` — Sanctum
No body. Scoped access; cross-tenant → 403/404.

## Webhook Logs (`WebhookLogsController`)

### GET `/webhook-logs` — Sanctum
No body. Tenant-scoped list.

### GET `/webhook-logs/{id}` — Sanctum
No body. Scoped access; payload/log content correct.

## Public Plans (`Public\PlansController`)

### GET `/public/plans` — API Key
No body (API key sent via header, e.g. `X-Api-Key`).
Test cases: valid key returns only that app's/tenant's active plans; missing key → 401; invalid/revoked key → 401; expired key → 401.

## Public Subscribe (`Public\SubscribeController`)

### POST `/public/subscribe` — API Key
```json
{
  "plan_id": "integer, required, exists:plans,id",
  "external_id": "string, required, max:255",
  "phone_number": "string, required, max:30",
  "email": "string, optional, nullable, email, max:255",
  "environment": "string, optional, in:live,test"
}
```
Test cases: valid subscribe creates subscription+transaction atomically (transaction rollback test); invalid plan_id → 422; already-subscribed subscriber (upgrade/duplicate) behavior; missing/invalid API key → 401; payment provider failure rolls back subscription+transaction together; missing required fields → 422.

## Semoa Payment Callback (`Public\SemoaCallbackController`)

### POST `/public/semoa/callback/{tenant}` — JWT (signed)
Body is a **raw signed JWT** (not JSON), decoded via the tenant's `semoa_api_key`. Decoded payload shape (from Semoa):
```json
{
  "merchant_reference": "string — matches Transactions.id",
  "state": "string — one of Paid, Error, Canceled, Pending, Partial, Excess",
  "order_reference": "string, optional — provider's own tx id",
  "received_amount": "numeric, optional",
  "payments": "array, optional"
}
```
Test cases: valid signed payload updates transaction/subscription status; invalid/tampered signature rejected; unknown tenant → 404; unmatched `merchant_reference` → 404 + logged; `state=Paid` activates subscription + sends `PaymentReceivedNotification`; `state=Error|Canceled` cancels subscription + sends `PaymentFailedNotification`; other states leave subscription pending; every attempt (success or failure) writes a `WebhookLogs` row; external app webhook notified with HMAC signature when `webhook_url` configured; malformed/unparseable payload rejected (500) and logged.

---

## Cross-cutting

- **Tenant scoping middleware**: verify every Sanctum-protected resource endpoint enforces tenant isolation (user from tenant A cannot read/write tenant B's data).
- **API key middleware (`api.key`)**: valid/invalid/missing/expired/revoked key handling shared across all `public/*` routes.
- **`authorize() => false` requests**: `StoreSubscribersRequest`, `UpdateSubscribersRequest`, `StoreSubscriptionsRequest`, `UpdateSubscriptionsRequest`, `StoreTransactionsRequest`, `UpdateTransactionsRequest`, `StoreWebhookLogsRequest`, `UpdateWebhookLogsRequest`, `UpdateTenantInvitationsRequest` all hard-return `false` — these resources are written internally (subscribe flow, callback), not via user-facing store/update routes. Confirm no route actually exposes create/update for these where `authorize()` would always 403.
- **Rate limiting** (if configured) on public/auth endpoints.
- **Notifications**: `PaymentFailedNotification` / `PaymentReceivedNotification` sent with correct recipient (tenant owners/admins) and content on respective transaction outcomes.
