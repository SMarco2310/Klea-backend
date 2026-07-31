# Controllers Implementation Plan

## Scope

Build out 13 controllers currently stubbed/broken in `klea-backend/app/Http/Controllers/`:

- `AuthController` (empty stub)
- `UserController` (currently `abstract`, unusable)
- 10 resource CRUD controllers: Tenants, Applications, Plans, Features, Subscribers, Subscriptions, Transactions, WebhookLogs, ApiKeys, TenantInvitations

Plus: Policies for tenant-scoped authorization, and middleware wiring (currently `bootstrap/app.php` has an empty `withMiddleware` block).

## Decisions locked in

- **Tenant scoping**: every resource query is scoped to `$request->user()->current_tenant_id`. Direct children of Tenant (Applications, Subscribers, TenantInvitations) filter on their own `tenant_id`. Grandchildren (Plans, Features, ApiKeys — belong to Applications) scope via relationship chain: `Plans::whereHas('application', fn($q) => $q->where('tenant_id', $tenantId))`.
- **Authorization**: Laravel Policy classes in `app/Policies/` (directory exists, currently empty), wired via `$this->authorize()` in controllers. One policy per tenant-scoped model.
- **Register flow**: `register()` creates a new `User` **and** a new `Tenant`, attaches the user to that tenant via the `tenant_user` pivot with `role = 'owner'`, sets `current_tenant_id`, issues a Sanctum token.
- **UserController vs AuthController split**: `AuthController` = identity/session (register, login, logout, future Clerk verify). `UserController` = actions on the already-authenticated user (profile show/update, switch active tenant).
- **Auth stack**: Sanctum personal access tokens now (works today, `personal_access_tokens` migration + `config/sanctum.php` already present). Clerk OAuth (Google/GitHub) is a **future** integration — stub an endpoint now, wire real Clerk JWT verification later once Clerk API keys/frontend SDK exist.
- **Tenant ownership model (revised)**: a user owns exactly **one** tenant, created automatically at `register()`. There is no self-serve "create another tenant" endpoint — `TenantsController::store` was removed, `POST /api/tenants` no longer exists (`Route::apiResource('tenants', ...)->except(['store'])`). The **only** way a user ends up belonging to more than one tenant is by accepting a `TenantInvitations` invite from another tenant's owner/admin. On accept, a new `tenant_user` row is created with `role` copied from the invitation (inviter chooses the role, e.g. can invite as `admin`, not just `member`). `UserController::switchTenant` moves the user's `current_tenant_id` between tenants they already belong to — this is the mechanism for multi-tenant membership, not tenant creation.
- **Stale `current_tenant_id` recovery**: `EnsureUserBelongsToCurrentTenant` middleware auto-heals instead of just blocking — if `current_tenant_id` is null or points at a tenant the user no longer belongs to (removed, tenant deleted), it falls back to another tenant the user belongs to (first match) and persists that as the new `current_tenant_id`; only returns 409 if the user belongs to zero tenants.

## Known bugs to fix while touching these files

| File | Bug |
|---|---|
| `ApiKeysController.php` | imports `StoreApi_KeysRequest`, `UpdateApi_KeysRequest`, `Api_Keys` — wrong names, real classes are `StoreApiKeysRequest`/`UpdateApiKeysRequest`/`ApiKeys` |
| `ApplicationsController.php` | imports `App\Http\Model\Applications` and `App\Http\Model\Users` — wrong namespace (`App\Models\...`), missing `use Illuminate\Http\Request;`, `Applications::find()` called with no arg (invalid) |
| `SubscriptionsController.php` | class doesn't `extends Controller` |
| `WebhookLogsController.php` | imports `StoreWebhook_LogsRequest`/`UpdateWebhook_LogsRequest` — wrong names |
| `UserController.php` | declared `abstract` — can't be instantiated/routed to at all |
| `AuthController.php` | empty, unused `use Illuminate\Http\Request;` |
| `app/Models/TenantUser.php` | `extends Pivot` but only imports `Illuminate\Database\Eloquent\Model` — missing `use Illuminate\Database\Eloquent\Relations\Pivot;`, will fatal on first use. Also missing `role` in `$fillable` (needed for owner/member distinction) |

---

## 1. Middleware plan

`bootstrap/app.php` currently has an empty `withMiddleware()` closure — nothing is registered. Laravel 11+ style config, so middleware goes here, not in a `Kernel.php`.

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->api(prepend: [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    ]);

    $middleware->alias([
        'tenant.member' => \App\Http\Middleware\EnsureUserBelongsToCurrentTenant::class,
    ]);
})
```

- **`auth:sanctum`** — applied at the route-group level (see routing section) to every resource route. Built into Sanctum, no custom code needed.
- **`EnsureUserBelongsToCurrentTenant`** (new, custom) — thin guard that runs after `auth:sanctum`: confirms `$request->user()->current_tenant_id` is not null and that the user still has a `tenant_user` row for it (covers the edge case where a user was removed from a tenant but the stale id lingers on their record). Returns 409/403 with a clear "no active tenant" message if not. This is a cheap sanity check; the real per-resource ownership check is the Policy layer below.
- Route groups in `routes/api.php`:
  ```php
  Route::post('/register', [AuthController::class, 'register']);
  Route::post('/login', [AuthController::class, 'login']);
  Route::post('/auth/clerk', [AuthController::class, 'loginWithClerk']); // stub

  Route::middleware(['auth:sanctum', 'tenant.member'])->group(function () {
      Route::post('/logout', [AuthController::class, 'logout']);
      Route::get('/me', [UserController::class, 'show']);
      Route::patch('/me', [UserController::class, 'update']);
      Route::post('/me/switch-tenant', [UserController::class, 'switchTenant']);

      Route::apiResource('tenants', TenantsController::class);
      Route::apiResource('applications', ApplicationsController::class);
      Route::apiResource('plans', PlansController::class);
      Route::apiResource('features', FeaturesController::class);
      Route::apiResource('subscribers', SubscribersController::class);
      Route::apiResource('subscriptions', SubscriptionsController::class);
      Route::apiResource('transactions', TransactionsController::class);
      Route::apiResource('webhook-logs', WebhookLogsController::class);
      Route::apiResource('api-keys', ApiKeysController::class);
      Route::apiResource('tenant-invitations', TenantInvitationsController::class);
  });
  ```
- Route-model-binding param names from `apiResource()` above (singular: `tenant`, `application`, `feature`, `plan`, `subscriber`, `subscription`, `transaction`, `webhook_log`, `api_key`, `tenant_invitation`) — **must match** what the `Rule::unique(...)->ignore($this->route('...'))` calls in the Update Request classes already assume. Cross-check once routes are wired; a couple of the guessed names (e.g. `tenant_invitation` vs Laravel's default kebab-to-param behavior) need verification against `php artisan route:list`.

## 2. Auth plan: Sanctum now, Clerk later

### Sanctum (build now)

- `POST /register` → `AuthController::register`
  1. Validate (`RegisterRequest`: name, email unique, password confirmed).
  2. `DB::transaction()`: create `User`, create `Tenant` (name derived from request or "{name}'s Workspace"), insert `TenantUser` pivot row (`tenant_id`, `user_id`, `role: 'owner'`), set `user->current_tenant_id`, save.
  3. `$token = $user->createToken('api')->plainTextToken;`
  4. Return `201` with user + token.
- `POST /login` → `AuthController::login`
  1. Validate (`LoginRequest`: email, password).
  2. `Auth::attempt()` or manual `Hash::check`.
  3. Issue token same as above.
  4. Return `200` with user + token.
- `POST /logout` → `AuthController::logout` — `$request->user()->currentAccessToken()->delete();`
- Every protected route requires header `Authorization: Bearer <token>`, enforced by `auth:sanctum` middleware.

### Clerk (stub now, wire later)

- `POST /auth/clerk` → `AuthController::loginWithClerk` — **stub only in this pass**, returns `501 Not Implemented` with a body explaining the intended contract, e.g.:
  ```json
  { "success": false, "message": "Clerk auth not yet configured. Expected: verify Clerk session JWT against Clerk JWKS, find-or-create local User by email, issue Sanctum token." }
  ```
- **Future implementation** (once Clerk keys exist), the intended flow:
  1. Frontend completes Google/GitHub OAuth via Clerk's SDK, obtains a Clerk session token.
  2. Frontend sends that token to `POST /auth/clerk`.
  3. Backend verifies the token against Clerk's JWKS endpoint (via `firebase/php-jwt` or Clerk's PHP SDK if one exists) — confirms signature + expiry, extracts Clerk user id/email.
  4. Backend does `User::firstOrCreate(['email' => $clerkEmail], [...])` — if new, run the same Tenant-creation flow as `register()`.
  5. Backend issues its own Sanctum token and returns it — **the frontend only ever talks to our API using the Sanctum token after this point**, Clerk is only involved in the initial identity handshake.
  6. This keeps Clerk swappable/optional later and keeps the rest of the API (all the `auth:sanctum` protected routes) completely unaware Clerk exists.
- Env vars to add when this gets built: `CLERK_JWKS_URL`, `CLERK_ISSUER`.

## 3. Policies (`app/Policies/`)

One per tenant-scoped model, registered in a `AuthServiceProvider`-equivalent (Laravel 11 auto-discovers policies by naming convention `{Model}Policy` if placed in `app/Policies/`, otherwise register in `bootstrap/app.php` or a provider).

Pattern (direct-child models — Applications, Subscribers, TenantInvitations):
```php
public function view(User $user, Applications $application): bool
{
    return $application->tenant_id === $user->current_tenant_id;
}
```

Pattern (grandchild models — Plans, Features, ApiKeys):
```php
public function view(User $user, Plans $plan): bool
{
    return $plan->application->tenant_id === $user->current_tenant_id;
}
```

`Tenants` itself: policy checks the tenant is one the user belongs to (via `tenant_user`), not just `current_tenant_id`, since a user can view/switch between multiple tenants they're a member of.

`Subscriptions`/`Transactions`/`WebhookLogs`: chain further (`subscription->subscriber->tenant_id`, `transaction->subscription->subscriber->tenant_id`).

Controllers call `$this->authorize('view', $model);` (or `update`/`delete`) at the top of `show`/`update`/`destroy`. `store` methods authorize against the parent (e.g. authorize the `application_id` in the request actually belongs to current tenant) before creating.

## 4. Controller pattern (all 10 resource controllers)

```php
public function index(Request $request)
{
    try {
        $tenantId = $request->user()->current_tenant_id;
        $items = Applications::where('tenant_id', $tenantId)->paginate(20);
        return response()->json(['data' => $items, 'success' => true], 200);
    } catch (\Exception $e) {
        Log::error('Error fetching applications: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to fetch', 'error' => $e->getMessage()], 500);
    }
}
```

`store`/`update`/`destroy` follow the same try/catch/Log/JSON shape, plus the Policy `authorize()` call and Request validation already built in the previous pass.

## 5. Billing model change: upgrade-only, immediate, prorated-credit

Klea's billing simplified — **no downgrades**, all plan changes are immediate upgrades. This replaces the earlier upgrade-now/downgrade-later design.

- **`scheduled_plan_id` is confirmed gone for good** — already removed from `Subscriptions` model, migration, and `StoreSubscriptionsRequest` in the earlier pass. No further schema change needed there, but grep the codebase once controllers are written to make sure nothing new reintroduces it.
- **No wallet/credit table.** Unused value of the current plan is computed as a prorated credit at upgrade time and applied inline in the same transaction — never persisted as a standalone balance.
- **Single service, not two.** Collapses the planned `SubscriptionUpgradeService` + `SubscriptionDowngradeService` split into one `SubscriptionUpgradeService` (`app/Services/SubscriptionUpgradeService.php`, new directory).
- **Proration math** (floored at 0, rounded down in subscriber's favor):
  ```php
  $daysRemaining = max(0, now()->diffInDays($subscription->expires_at, false));
  $dailyRate = $subscription->plan->price / $subscription->plan->duration_days;
  $unusedCredit = floor($dailyRate * $daysRemaining); // rounded down, floored at 0 by max() above
  $amountDue = max(0, $newPlan->price - $unusedCredit);
  ```
- **Upgrade flow** (`SubscriptionUpgradeService::upgrade(Subscriptions $current, Plans $newPlan)`):
  1. Compute `$amountDue` as above.
  2. `DB::transaction()`:
     - Set old `$subscription->status = 'cancelled'`, `cancelled_at = now()`, save.
     - Create new `Subscriptions` row: `plan_id = $newPlan->id`, `status = 'active'`, `starts_at = now()`, `expires_at = now()->addDays($newPlan->duration_days)`.
     - Create `Transactions` row for `$amountDue` against the new subscription (even if `$amountDue === 0`, still log a zero-amount transaction for audit trail — confirm this with billing reconciliation needs, flagged as an assumption).
  3. Return the new `Subscriptions` model.
- **Controller wiring**: `SubscriptionsController` gets a new endpoint/action, not just plain `store()`:
  - `POST /subscriptions` (`store`) — first-time subscribe, no proration, straightforward create.
  - `POST /subscriptions/{subscription}/upgrade` (new `upgrade` method) — takes `plan_id` in the request, validates it's a **higher-priced** plan than current (reject same-or-lower price with a 422 — this is what enforces "upgrade-only" at the API boundary), calls `SubscriptionUpgradeService::upgrade()`.
- **New Request class**: `UpgradeSubscriptionRequest` — `plan_id: required|exists:plans,id`, plus a custom rule/closure checking `Plans::find($value)->price > $subscription->plan->price`.
- **Removed from earlier plan**: any notion of a downgrade endpoint, scheduled-plan-change cron/job, or credit-balance column — none of that gets built.

## Build order

1. ~~Fix `TenantUser.php` import + add `role` to fillable.~~ **DONE** — `role` added to `$fillable`. (Also had to fix a follow-on typo: import was `Illuminate\Database\Eloquent\Relational\Pivot` (wrong namespace, doesn't exist) — corrected to `Illuminate\Database\Eloquent\Relations\Pivot`.)
2. Fix the 4 known controller bugs (imports, missing `extends Controller`). — **not done yet**
3. ~~Un-abstract `UserController`.~~ **DONE** — class no longer `abstract`, body still empty pending step 6.
4. Write `EnsureUserBelongsToCurrentTenant` middleware, register in `bootstrap/app.php`. — **not done yet**
5. Write `AuthController` (register/login/logout/clerk-stub). — **not done yet**, still an empty stub.
6. Write `UserController` (show/update/switchTenant). — **not done yet**, still an empty body.
7. Write 10 Policy classes. — **not done yet**
8. Write 10 resource controllers. — **not done yet**
9. Write `SubscriptionUpgradeService` + `UpgradeSubscriptionRequest` + wire `SubscriptionsController::upgrade`. — **design confirmed (section 5 above), code not written yet**
10. Wire `routes/api.php` (including `POST /subscriptions/{subscription}/upgrade`). — **not done yet**
11. Run `php artisan route:list` to verify route-model-binding param names match the `Rule::unique()->ignore($this->route(...))` assumptions made in the Update Request classes. — **not done yet**

**Note on `Tenants.php`**: `'role'` was also added to `Tenants::$fillable` — this looks misplaced. `role` (owner/member) belongs on the `tenant_user` pivot (per step 1, already there), not on the `Tenants` model itself, which has no per-user role concept. Flagging for a decision, not auto-removing.
