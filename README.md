# ORIN Core

Conversational commerce engine for Bangladesh.

A merchant connects a WhatsApp number. ORIN's AI reads incoming messages in **Banglish, Bengali, or English**, quotes **verified** prices from the merchant's own catalog, takes **cash-on-delivery orders**, and books the parcel with **Steadfast** or **Pathao** — without a human touching it.

**Stack:** PHP 8.1+ (no framework, no Composer runtime deps) · MySQL · cPanel-ready.

> Private repository. Contains business logic. Never make public without rotating every secret.

---

## Why this exists

90% of Bangladeshi online businesses sell through WhatsApp. They have no CRM, no order tracking, no customer history, no follow-up — they write orders in a notebook and paste addresses into courier portals by hand.

Foreign tools do not solve this. They fail on Banglish (`bhai eta ki available?`), they hallucinate prices, and they have never heard of Steadfast or COD.

ORIN Core is built for exactly this. Nothing else.

---

## What makes it different

| Capability | Why it matters here |
|---|---|
| **Banglish understanding** | Customers type Romanized Bengali. Most bots choke on it. |
| **Anti-hallucination Price Guard** | The AI **cannot** state a price that is not in the catalog. Wrong price = merchant loses money. |
| **Steadfast + Pathao auto-booking** | One click in chat becomes one consignment. No portal copy-paste. |
| **COD order flow** | Address + phone extraction from freeform chat, then courier dispatch. |
| **Resilient AI failover** | Four-provider chain with key detection. A dead key never silences the bot. |
| **cPanel-native** | Merchants already have cPanel hosting. No Docker, no VPS, no DevOps bill. |

---

## Architecture

See [`ARCHITECTURE.md`](ARCHITECTURE.md) for the full design. Short version:

```
WhatsApp / Messenger / Instagram
        |
        v  webhook (signature verified)
   InboundPipeline
        |-- dedup (message_id)
        |-- tenant resolve (business_id)
        |-- quota gate      (billing)
        |-- handoff gate    (human owns thread?)
        |-- prompt build    (Banglish system prompt + catalog + KB)
        v
   ResilientAIEngine  --- Gemini / OpenAI / OpenRouter / Claude
        |                 (skips providers with no key)
        |-- tool calls  ->  catalog lookup, order create, courier book
        v
   PriceGuard  --- every ৳ in the reply must exist in catalog
        |
        v
   HumanFeel splitter -> channel adapter -> out
```

---

## Layout

```
config/schema.sql          Database schema (single source of truth)
src/AI/                    AI engine, providers, Price Guard, prompts
src/AI/Providers/          Gemini, OpenAI, OpenRouter adapters
src/Core/                  DB, config, logging, security
src/Commerce/              Catalog, orders, customers
src/Logistics/             Steadfast, Pathao adapters
src/Channels/              WhatsApp, Messenger, Instagram adapters
src/Webhooks/              Inbound entrypoints
src/Admin/                 Tenant admin panel
cron/                      Workers: queue, follow-up, billing
```

---

## Setup (cPanel)

1. Create a MySQL database and user in cPanel.
2. Import the schema:
   ```bash
   mysql -u USER -p DBNAME < config/schema.sql
   ```
3. Copy `.env.example` to `.env`, fill every value.
4. Point the document root at `public/`.
4. Add cron jobs — at minimum the queue worker, every minute.
5. Open the admin panel and complete onboarding.

### Webhook URLs to register with Meta

| Channel | URL |
|---|---|
| WhatsApp | `https://your-domain/webhooks/whatsapp.php` |
| Messenger | `https://your-domain/webhooks/messenger.php` |
| Instagram | `https://your-domain/webhooks/instagram.php` |

---

## Operating rules

- **Every AI provider needs a key.** The engine skips any provider whose key is empty. If all are empty, it throws a clear error instead of failing silently.
- **The Price Guard is not optional.** It runs on every outbound AI reply. A number that is not in the catalog is a bug, not a feature.
- **Tenant scope is enforced at the query layer.** Every tenant table is filtered by `business_id`; the linter in `tools/lint_tenant_scope.php` fails the build otherwise.
- **Handoff is a gate, not a hint.** When `conversations.handoff_status` is `active` or `requested`, the AI does not send. Ever.

---

## License

Proprietary. All rights reserved.
