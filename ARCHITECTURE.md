# ORIN Core — Architecture

> This is the design document. It is written to be read in one sitting by
> whoever maintains this next — including a future you who has forgotten
> how any of it works.

---

## 1. What ORIN Core is

A multi-tenant conversational commerce engine for Bangladeshi merchants.
A merchant connects a WhatsApp number. The AI reads incoming messages,
answers from that merchant's own catalog, takes cash-on-delivery orders,
and books the parcel with a courier — while a human can take over any
conversation at any moment.

**Stack:** PHP 8.1+, MySQL 8, no framework, no Composer runtime deps.
Runs on cPanel shared hosting, because that is what the merchants have.

---

## 2. The two things this system must never get wrong

Everything below exists to protect these two invariants:

1. **The AI never states a price that is not in the merchant's catalog.**
   A hallucinated price in a COD order is a direct loss the merchant pays.
   Enforced by `PriceGuard`, which runs on every outbound AI reply.

2. **A tenant never sees another tenant's data.**
   Every tenant-scoped table carries `business_id`; every query filters on
   it; a linter fails the build on any query that does not.

If a change weakens either of these, the change is wrong.

---

## 3. Layers

```
public/                 Document root. Only entry scripts live here.
  webhooks/             whatsapp.php, messenger.php, instagram.php
  admin/                Tenant panel
  api/                  Front controller for /api/v1/*

src/AI/                 Provider adapters, ResilientAIEngine, PriceGuard, prompts
src/Core/               DB (PDO), config, logger, crypto, clock
src/Commerce/           Catalog, orders, customers
src/Logistics/          Steadfast, Pathao adapters
src/Channels/           WhatsApp, Messenger, Instagram send/receive
src/Webhooks/           Signature verification, payload normalisation

cron/                   worker.php, followup.php, billing.php, backup.php
config/schema.sql       The schema. Single source of truth.
tools/                  lint_tenant_scope.php, migrate.php
```

---

## 4. Inbound message flow

```
1.  Meta POSTs to /webhooks/whatsapp.php
2.  Signature verified with hash_equals against the app secret.
    A failed signature is logged and dropped with HTTP 403.
3.  Payload normalised into a Message DTO:
    { business_external_id, customer_external_id, body, type, provider_message_id }
4.  Tenant resolved: WA_PHONE_NUMBER_ID -> businesses.id
    No match -> log and drop. Never guess.
5.  Dedup on messages.external_id (unique per business).
    A duplicate means Meta retried; return 200 and stop.
6.  Message stored, conversation.last_message_at updated.

--- gates, in order ---

7.  Quota gate.      subscription_status, trial expiry, ai_message_quota
                     vs ai_messages_used, wallet_balance.
                     Fails -> escalate to human. Never silent.
8.  Handoff gate.    conversations.handoff_status in (active, requested)
                     -> AI stays out of this thread entirely.
9.  Complaint gate.  Angry / refund / legal keywords -> escalate immediately.

--- generation ---

10. Prompt assembled: Banglish system prompt + catalog price list +
    knowledge base excerpts + last N turns of history.
11. ResilientAIEngine.chat(...) — the failover chain.
12. Tool calls executed: search_catalog, add_to_cart, create_order,
    request_human. Every tool output is validated before use.
13. PriceGuard.inspect(reply).
    - passed  -> send
    - failed  -> regenerate once with the verified prices pinned in the
                 prompt; if it fails again, redact and escalate.
14. Human-feel splitter breaks the reply into short messages with delays.
15. Channel adapter sends via the Graph API.
16. Message stored as outbound, usage meter incremented.
```

---

## 5. Human-agent flow

The admin inbox calls the same send path. When an agent sends, the thread
is marked `handoff_status = active` and the AI stops replying to it.

The AI path and the agent path resolve credentials through the same
`CredentialResolver`, so when a token goes invalid both break together —
which is deliberate, because it means there is exactly one place to fix.

---

## 6. AI provider failover

`ResilientAIEngine` is the only thing in the system that talks to an LLM
provider directly.

```
LLM_PROVIDER            = openrouter
FALLBACK_LLM_PROVIDER   = gemini,openai

chain built at construction:
  openrouter  -> isConfigured()? no key -> SKIPPED, logged
  gemini      -> key present          -> chain[0]
  openai      -> no key               -> SKIPPED, logged

result: chain = [gemini]
```

Rules:

- A provider with no API key is **never** in the chain. It is skipped at
  build time, not discovered at request time.
- HTTP 429 moves to the next provider immediately. No retry.
- Transient failures (5xx, network) retry once on the same provider with
  250 ms backoff, then move on.
- If the chain is empty or fully exhausted, the engine throws
  `AllProvidersExhaustedException` with the full attempt log. The caller
  escalates to a human and surfaces the error in the admin panel.

**Never** configure the same provider as both primary and first fallback.
That was the original bug: the chain retried the rate-limited provider
before trying anything else.

---

## 7. Multi-tenancy

Every tenant table has `business_id`. Every query filters on it. The
helpers in `src/Core/TenantScope.php` take a `business_id` and return a
WHERE fragment plus bound params; `tools/lint_tenant_scope.php` scans raw
SQL and fails the build on any tenant table touched without a
`business_id` predicate.

No exceptions. Not for admin queries, not for reports, not for
"temporary" debugging.

---

## 8. What is deliberately missing

These were considered and rejected for v1. They may arrive later; they
are not oversights.

- **Vector embeddings / semantic RAG.** MySQL FULLTEXT over product names,
  aliases, and knowledge bodies handles a catalog of a few thousand
  items. Embeddings add a dependency (pgvector or an external API) and a
  cost per query. Revisit when a merchant exceeds that scale.
- **A visual flow builder.** Rules are code for now. Merchants do not ask
  for it yet.
- **Multiple couriers per order.** One courier per order, chosen by the
  merchant's default. Multi-courier routing is a v2 problem.
- **Real-time websockets.** The admin inbox polls. SSE is a v2 nicety.

---

## 9. Reading order for a new maintainer

1. This file.
2. `config/schema.sql` — the exact shape of everything.
3. `src/AI/ResilientAIEngine.php` — the one piece that fails loudly.
4. `src/AI/PriceGuard.php` — the one piece that protects revenue.
5. `public/webhooks/whatsapp.php` — the reference pipeline.
