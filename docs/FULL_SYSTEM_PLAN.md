# Orin Full System - Plan & Architecture

> Stack: **Pure PHP 8.1+ / MySQL / no framework**.
> Repo: `tanvir-hasan11/orin-core`.

## 1. Goal

One codebase, three surfaces, on top of the existing `Orin\AI` engine:

1. **Public website** - landing, pricing, signup, login, docs, contact.
2. **Merchant area** - merchant login + panel (dashboard, API keys, usage,
   billing, settings).
3. **Super admin area** - admin login + panel (merchants, plans, providers,
   global settings, audit, impersonation).

## 2. Roles & responsibility boundary

| Concern | Owner |
|---|---|
| Which AI providers are enabled | **super admin** |
| Platform provider API keys | **super admin** |
| Provider pricing / markup | **super admin** |
| Plans, quotas, subscriptions | **super admin** |
| Creating/revoking their own API keys | merchant |
| Seeing their own usage & cost | merchant |
| Their profile / password | merchant |

**Rule:** a merchant never selects or configures an AI provider. Merchants are
consumers of the Orin API. Provider selection, keys and cost model are decided
by the platform owner in the super admin panel.

## 3. Directory layout

```
public/                     <- web root
  index.php                 <- front controller
app/
  Core/                     <- Router, Request, Response, Database, View,
                               Session, Csrf, Validator, RateLimiter, App
  Middleware/               <- Throttle, Csrf, Auth, Role
  Http/Controllers/
    Public/                 <- Home, Auth, Signup, PasswordReset
    Merchant/               <- Dashboard, ApiKey, Usage, Billing, Settings
    Admin/                  <- Dashboard, Merchant, Plan, Provider, Audit
  Models/                   <- User, Merchant, ApiKey, Plan, ...
  Services/                 <- Auth, Audit, Merchant, ApiKey, AiGateway, Usage
  Repositories/             <- SQL per aggregate
routes/
  web.php  api.php
resources/views/
  layouts/ public/ merchant/ admin/
config/
  app.php  database.php  config.php  schema.sql
storage/
  logs/  cache/  sessions/
tests/
```

## 4. Merchant API (phase 4)

```
POST /api/v1/complete
Headers: Authorization: Bearer orin_xxx_yyy
Body: { "prompt": "...", "options": {...} }

Pipeline: key -> merchant active -> plan quota -> price guard
          -> AiGateway -> Orin\AI\ResilientAIEngine -> provider
          -> usage_logs + usage_monthly rollup -> JSON response

Note: the provider is chosen by the platform's enabled set, never by the merchant.
```

## 5. Build phases

- Phase 0 - plan
- Phase 1 - core plumbing + full schema  [done]
- Phase 2 - auth, roles, signup/login/reset  [done]
- Phase 3 - merchant panel (keys, usage, billing, settings)  [done]
- Phase 4 - merchant API gateway  [next]
- Phase 5 - super admin panel (merchants, plans, providers, settings, audit)
- Phase 6 - public site polish
- Phase 7 - hardening (2FA, webhooks, invoices, backups)
