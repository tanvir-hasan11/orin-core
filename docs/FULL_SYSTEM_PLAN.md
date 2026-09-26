# Orin Full System - Plan & Architecture

> Stack: **Pure PHP 8.1+ / MySQL / no framework**.
> Repo: `tanvir-hasan11/orin-core`.

## 1. What ORIN actually is

ORIN is not an AI API wrapper. It is a conversational commerce platform for
Bangladeshi businesses.

A merchant connects their WhatsApp number and their Facebook page. A customer
sends a message. ORIN:

1. receives it on the merchant's own channel,
2. captures the person as a lead,
3. answers according to **that merchant's business type** - a clothing shop,
   a restaurant and a clinic do not get the same assistant,
4. moves the lead along the merchant's own pipeline,
5. hands the thread to a human the moment that is the right thing to do.

## 2. Responsibility boundary

| Concern | Owner |
|---|---|
| Which AI providers are enabled, their keys and priority | **super admin** (`platform_providers`) |
| Plans, quotas, subscriptions | **super admin** |
| Connecting their own WhatsApp / Messenger account | merchant |
| Business profile: vertical, tone, hours, delivery | merchant |
| Reading conversations, taking over, replying | merchant |
| Working the lead pipeline | merchant |

A merchant never selects or configures an AI provider. Merchants are consumers
of the ORIN engine. Provider selection, keys and cost model are decided by the
platform owner in the super admin panel.

## 3. The inbound pipeline

```
Meta -> POST /webhooks/whatsapp | /webhooks/messenger
 1. verify X-Hub-Signature-256 against the app secret
 2. resolve the merchant from the phone_number_id / page_id
    (channel_connections) - no match means drop, never guess
 3. de-duplicate on messages.external_id (Meta retries)
 4. upsert the contact, open or reuse the conversation
 5. store the inbound message
 6. capture the lead (one per contact)
 7. handoff gate - if a human owns the thread, stop here
 8. build the prompt:
      vertical rules (config/verticals.php)
      + merchant profile (description, tone, hours, delivery, area)
      + contact facts and lead stage
      + last N turns of the transcript
 9. AiGateway -> platform provider chain -> ResilientAIEngine
10. strip machine directives, send the clean text back
11. apply directives: lead stage, captured fields, handoff request
12. record usage
```

## 4. Reply directives

The engine supports several providers, not all of which expose tool calling
cleanly through a single interface. So the AI appends directives to its own
reply and ORIN strips them before the customer sees anything:

```
[[LEAD:stage=qualified]]
[[LEAD:phone=01712345678]]
[[LEAD:stage=won|phone=01712345678|address=House 12, Road 4, Nasirabad]]
[[HANDOFF:refund request]]
```

Only the fields `phone`, `name`, `email`, `address`, `interest` and the stages
declared by the merchant's own vertical are accepted. Everything else is
dropped, so a model cannot write arbitrary columns into the database.

## 5. Verticals

`config/verticals.php` holds one prompt pack per business type. Each pack
declares a goal, the rules the assistant must follow, the lead fields that
matter and the pipeline stages for that kind of business.

Shipping verticals: `ecommerce`, `restaurant`, `clinic`, `real_estate`,
`service`, `education`, plus `generic` as the fallback.

Adding a vertical is a config edit, not a code change.

## 6. Directory layout

```
public/                     <- web root
  index.php                 <- front controller
app/
  Core/                     <- Router, Request, Response, Database, View,
                               Session, Csrf, Validator, RateLimiter, Crypto, App
  Middleware/               <- Throttle, Csrf, Auth, Role
  Channels/                 <- WhatsAppClient, MessengerClient
  Http/Controllers/
    Public/                 <- Home, Auth, Signup, PasswordReset
    Webhook/                <- WhatsAppWebhook, MessengerWebhook
    Merchant/               <- Dashboard, Inbox, Lead, Channel, Profile,
                               ApiKey, Usage, Billing, Settings
    Admin/                  <- Dashboard, Merchant, Plan, Provider, Audit
  Services/                 <- Auth, Audit, Merchant, ApiKey, AiGateway,
                               Conversation, Lead, Channel, OutboundSender,
                               VerticalRegistry, VerticalPromptBuilder,
                               ReplyDirectiveParser, InboundMessageProcessor
  Models/                   <- User, Merchant, ApiKey
routes/
  web.php  api.php
resources/views/
  layouts/ public/ merchant/ admin/
config/
  app.php  database.php  config.php  verticals.php  schema.sql  migrations/
tests/
```

## 7. Build phases

- Phase 0 - plan
- Phase 1 - core plumbing + full schema  [done]
- Phase 2 - auth, roles, signup/login/reset  [done]
- Phase 3 - merchant panel (keys, usage, billing, settings)  [done]
- Phase 4 - channels, conversations, lead capture, vertical-aware replies  [done]
- Phase 5 - super admin panel (merchants, plans, platform providers, settings, audit)
- Phase 6 - public site polish (pricing, docs, contact)
- Phase 7 - follow-up automation, catalogue sync, courier booking, hardening
