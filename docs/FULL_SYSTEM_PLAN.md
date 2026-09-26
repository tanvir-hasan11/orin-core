# Orin Full System - Plan & Architecture

> Status: **planning** (no application code written yet).
> Stack: **Pure PHP 8.1+ / MySQL / no framework**.
> Repo: `tanvir-hasan11/orin-core` (this repo).

This document is the single source of truth for what we are going to build. We
write the plan first, agree on it, then implement module by module.

---

## 1. Goal

One codebase that ships three surfaces on top of the existing `Orin\AI` engine:

1. **Public website** - landing pages, pricing, signup, docs, contact.
2. **Merchant area** - merchant login + control panel (dashboard, API keys,
   usage, billing, AI provider settings).
3. **Super admin area** - admin login + control panel (manage merchants, plans,
   providers, quotas, audit log, impersonation).

The existing `src/AI/*` (ResilientAIEngine, providers, PriceGuard, Banglish)
stays untouched and becomes the engine every merchant request runs through.

---

## 2. Directory layout (target)

```
public/                     <- web root (only this is exposed)
  index.php                 <- front controller (all requests enter here)
  assets/
    css/  js/  img/
  uploads/                  <- merchant uploads (guarded)

app/
  Core/
    Router.php              <- simple regex/attribute router
    Request.php  Response.php
    Database.php            <- PDO wrapper (MySQL, prepared stmts)
    View.php                <- plain PHP templates
    Session.php  Csrf.php   <- session + CSRF tokens
    Container.php  Config.php
    Validator.php
    RateLimiter.php
  Middleware/
    AuthMiddleware.php
    MerchantMiddleware.php
    AdminMiddleware.php
    CsrfMiddleware.php
    ThrottleMiddleware.php
  Http/Controllers/
    Public/                 <- HomeController, PricingController, SignupController, ContactController
    Merchant/               <- AuthController, DashboardController, ApiKeyController,
                               UsageController, BillingController, ProviderController
    Admin/                  <- AuthController, DashboardController, MerchantController,
                               PlanController, ProviderController, AuditController, ImpersonateController
  Models/
    User.php  Merchant.php  ApiKey.php  UsageLog.php
    Plan.php  Subscription.php  Invoice.php  AuditLog.php  Setting.php
  Services/
    AuthService.php         <- login/logout, password hashing, remember-me
    MerchantService.php
    ApiKeyService.php       <- generate/hash/rotate keys
    BillingService.php      <- plan -> quota -> invoice
    UsageService.php        <- token accounting, monthly rollups
    AuditService.php        <- who did what, when
    AiGateway.php           <- wraps Orin\AI\ResilientAIEngine for HTTP/API calls
  Repositories/             <- DB access per model (keeps SQL out of controllers)

routes/
  web.php                   <- public + panel routes
  api.php                   <- /api/v1 routes (merchant AI calls)

resources/views/
  layouts/  public/  merchant/  admin/  emails/

storage/
  logs/  cache/  sessions/

config/
  config.php  database.php  routes.php
  schema.sql               <- full DB schema (extends the current one)
  migrations/              <- 001_..., 002_...

tests/                      <- PHPUnit (unit + feature)
```

`src/AI/*` and `src/Support/*` (already built) stay as the engine layer and are
autoloaded as `Orin\...`.

---

## 3. Roles & permissions

| Role          | Can do                                                                 |
|---------------|------------------------------------------------------------------------|
| `guest`       | view public site, signup, login, contact                              |
| `merchant`    | everything inside their own merchant account                          |
| `admin`       | read/write all merchants, plans, providers, quotas, audit             |
| `super_admin` | admin + create/disable admins, change global settings, impersonate    |

Rule: a merchant can only ever see rows where `merchant_id = session.merchant_id`.
Enforced centrally in `Repository` base class, not per-controller.

---

## 4. Database schema (additions)

Existing (keep): `provider_requests`, `provider_attempts`, `price_guard_events`, `prompt_cache`.

New tables:

```
users
  id, role ENUM('merchant','admin','super_admin'),
  email UNIQUE, password_hash, name,
  status ENUM('active','pending','suspended'),
  email_verified_at, last_login_at, created_at, updated_at

merchants
  id, owner_user_id FK->users, company_name, slug UNIQUE,
  status ENUM('trial','active','suspended'),
  plan_id FK->plans, trial_ends_at, created_at, updated_at

plans
  id, name, code UNIQUE, price_usd DECIMAL,
  monthly_token_quota BIGINT, max_api_keys INT,
  features JSON, is_active, created_at, updated_at

subscriptions
  id, merchant_id FK, plan_id FK, status,
  starts_at, ends_at, created_at, updated_at

api_keys
  id, merchant_id FK, label, key_prefix,
  key_hash, last_used_at, revoked_at,
  created_at, updated_at

usage_logs
  id, merchant_id FK, api_key_id FK NULL,
  provider, model, prompt_tokens, completion_tokens,
  cost_usd, latency_ms, status, created_at
  (rolled up monthly for quota checks)

usage_monthly
  id, merchant_id FK, period CHAR(7) -- YYYY-MM,
  tokens_used BIGINT, cost_usd DECIMAL,
  UNIQUE(merchant_id, period)

invoices
  id, merchant_id FK, period, amount_usd,
  status ENUM('draft','sent','paid','void'),
  issued_at, paid_at, pdf_path

audit_logs
  id, actor_user_id FK NULL, actor_role,
  action VARCHAR(64), target_type, target_id,
  ip, user_agent, meta JSON, created_at

settings
  key PRIMARY KEY, value TEXT, updated_at
  -- global toggles: default providers, signup open/closed, etc.

sessions
  id, user_id FK, ip, user_agent,
  payload, last_activity

password_resets
  email, token_hash, expires_at
```

All tables: InnoDB, utf8mb4, FKs with ON DELETE rules, indexes on every FK and
on `email`, `slug`, `key_prefix`, `(merchant_id, created_at)`.

---

## 5. Auth & security

- Password: `password_hash()` with `PASSWORD_ARGON2ID` (bcrypt fallback).
- Sessions: server-side, `session_regenerate_id(true)` on login, strict cookie
  flags (`HttpOnly`, `SameSite=Lax`, `Secure` in prod).
- CSRF: token per session, required on every POST/PUT/DELETE.
- API keys: shown **once** at creation; only `key_hash` stored (SHA-256).
  Format `orin_<prefix>_<secret>`.
- Rate limiting: per-IP on auth, per-API-key on `/api/v1`.
- Login throttling: 5 fails -> 15 min lock, logged to `audit_logs`.
- All SQL through PDO prepared statements. No string concatenation.
- Output escaped by default in `View` (`e()` helper).
- Secrets only from `.env`, never committed.
- Optional 2FA (TOTP) for `admin` / `super_admin` - phase 2.

---

## 6. Request lifecycle

```
Browser -> public/index.php
  -> Config + Container bootstrap
  -> Router matches (method + path)
  -> Middleware chain: Throttle -> Csrf -> Auth -> Role
  -> Controller -> Service -> Repository -> MySQL
                    |
                    +-> AiGateway -> Orin\AI\ResilientAIEngine -> providers
  -> View rendered / JSON returned
```

---

## 7. Public website pages

- `/` home (hero, features, how it works, CTA)
- `/pricing` plan comparison (reads `plans`)
- `/docs` API docs (static + generated snippets)
- `/signup` create merchant account
- `/login` unified login -> redirects by role
- `/contact` form (rate-limited, stored + email)
- `/legal/terms`, `/legal/privacy`

---

## 8. Merchant control panel

Routes under `/merchant`:

- `/merchant/dashboard` - usage this month, quota bar, recent calls
- `/merchant/api-keys` - list, create (shown once), revoke, rotate
- `/merchant/usage` - charts by day/provider, filter by key
- `/merchant/providers` - enable/disable OpenAI/Gemini/OpenRouter,
  set own keys (stored encrypted), set default model
- `/merchant/playground` - test a prompt, see provider + cost
- `/merchant/billing` - current plan, invoices, upgrade/downgrade
- `/merchant/settings` - profile, password, company info, webhook URL

---

## 9. Super admin control panel

Routes under `/admin`:

- `/admin/dashboard` - MRR, active merchants, total tokens/cost, failures
- `/admin/merchants` - list/search, view detail, suspend, reset quota
- `/admin/plans` - CRUD plans
- `/admin/subscriptions` - assign/change/cancel
- `/admin/providers` - global provider config + health check + price table
- `/admin/settings` - signup open/closed, default plan, feature flags
- `/admin/audit` - filterable audit log
- `/admin/admins` - create/disable admin users (super_admin only)
- `/admin/impersonate/{merchant}` - time-boxed, fully audited

---

## 10. Merchant API (what merchants call)

```
POST /api/v1/complete
Headers: Authorization: Bearer orin_xxx_yyy
Body: { "prompt": "...", "provider": "openai"?, "options": {...} }

Checks: key valid -> merchant active -> plan quota -> price guard
Then:   AiGateway -> ResilientAIEngine -> ProviderResponse
Writes: usage_logs (+ usage_monthly rollup)
Returns:{ "content": "...", "provider": "openai", "tokens": {...}, "cost_usd": 0.0012 }
```

Errors: 401 invalid key, 402 quota exceeded, 429 rate limited, 502 all
providers failed - each with a JSON error envelope.

---

## 11. Build phases

**Phase 0 - foundation (this doc + skeleton)**
- Finalise plan, freeze DB schema v1, set coding conventions.

**Phase 1 - core plumbing**
- Bootstrap, Router, Request/Response, Database, View, Session, Csrf,
  Container, Config, Validator, RateLimiter.
- Migration runner + `schema.sql` applied.

**Phase 2 - auth & roles**
- users/merchants/sessions/password_resets.
- Signup, login, logout, password reset, role middleware.

**Phase 3 - merchant panel**
- Dashboard, API keys, usage, providers, billing (mock), settings.

**Phase 4 - API gateway**
- `/api/v1/complete`, key auth, quota, price guard, usage logging,
  AiGateway bridging to `Orin\AI`.

**Phase 5 - super admin panel**
- Merchants, plans, subscriptions, providers, settings, audit, impersonate.

**Phase 6 - public site polish**
- Home, pricing, docs, contact, legal.

**Phase 7 - hardening**
- 2FA, webhooks, invoice PDF, backups, monitoring, load test.

Each phase ends with tests + a tagged release.

---

## 12. Conventions

- PSR-12 formatting, `declare(strict_types=1)` everywhere.
- Namespace root `Orin\` (same as existing engine).
- Controllers thin, Services hold logic, Repositories hold SQL.
- No framework, but framework-grade structure.
- Every write action writes to `audit_logs`.
- Feature tests via PHPUnit + a test MySQL database.

---

## 13. Open questions (to confirm before Phase 1)

1. Payment gateway - Stripe, or manual invoice only for now?
2. Email sending - SMTP creds, or queue for later?
3. Do merchants bring their **own** provider keys, or use platform keys
   with billing markup? (Plan above supports both - pick default.)
4. 2FA required for all admins from day one?
5. Language - English-only UI, or Bangla + English?
