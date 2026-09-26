-- Phase 4: channels, contacts, conversations, messages, leads, business profiles.
-- Idempotent. Safe to re-run.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS merchant_profiles (
    merchant_id   BIGINT UNSIGNED NOT NULL,
    business_type VARCHAR(32)     NOT NULL DEFAULT 'generic',
    description   TEXT            NULL,
    tone          VARCHAR(32)     NOT NULL DEFAULT 'friendly',
    greeting      VARCHAR(500)    NULL,
    working_hours VARCHAR(190)    NULL,
    delivery_info VARCHAR(500)    NULL,
    service_area  VARCHAR(190)    NULL,
    language      VARCHAR(16)     NOT NULL DEFAULT 'auto',
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (merchant_id),
    CONSTRAINT fk_merchant_profiles_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS channel_connections (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id         BIGINT UNSIGNED NOT NULL,
    channel             ENUM('whatsapp','messenger','instagram') NOT NULL,
    external_account_id VARCHAR(64)     NOT NULL,
    display_name        VARCHAR(190)    NULL,
    access_token        TEXT            NULL,
    app_secret          VARCHAR(255)    NULL,
    verify_token        VARCHAR(190)    NULL,
    status              ENUM('connected','error','disconnected') NOT NULL DEFAULT 'connected',
    last_error          VARCHAR(500)    NULL,
    connected_at        TIMESTAMP       NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_channel_account (channel, external_account_id),
    KEY idx_channel_merchant (merchant_id, channel),
    CONSTRAINT fk_channel_connections_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id   BIGINT UNSIGNED NOT NULL,
    channel       VARCHAR(24)     NOT NULL,
    external_id   VARCHAR(190)    NOT NULL,
    name          VARCHAR(190)    NULL,
    phone         VARCHAR(32)     NULL,
    email         VARCHAR(190)    NULL,
    address       TEXT            NULL,
    tags          JSON            NULL,
    metadata      JSON            NULL,
    first_seen_at TIMESTAMP       NULL,
    last_seen_at  TIMESTAMP       NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_contacts_external (merchant_id, channel, external_id),
    KEY idx_contacts_phone (merchant_id, phone),
    CONSTRAINT fk_contacts_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversations (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id      BIGINT UNSIGNED NOT NULL,
    contact_id       BIGINT UNSIGNED NOT NULL,
    channel          VARCHAR(24)     NOT NULL,
    status           ENUM('open','pending','closed') NOT NULL DEFAULT 'open',
    handoff_status   ENUM('none','requested','active') NOT NULL DEFAULT 'none',
    assigned_user_id BIGINT UNSIGNED NULL,
    message_count    INT UNSIGNED    NOT NULL DEFAULT 0,
    last_message_at  TIMESTAMP       NULL,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conv_merchant_recent (merchant_id, last_message_at),
    KEY idx_conv_handoff (merchant_id, handoff_status),
    CONSTRAINT fk_conversations_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE,
    CONSTRAINT fk_conversations_contact  FOREIGN KEY (contact_id)  REFERENCES contacts (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id    BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NOT NULL,
    external_id    VARCHAR(190)    NULL,
    direction      ENUM('in','out') NOT NULL,
    sender         ENUM('contact','ai','agent','system') NOT NULL,
    content_type   VARCHAR(24)     NOT NULL DEFAULT 'text',
    body           MEDIUMTEXT      NULL,
    raw_payload    JSON            NULL,
    ai_provider    VARCHAR(64)     NULL,
    ai_model       VARCHAR(128)    NULL,
    ai_tokens_in   INT UNSIGNED    NULL,
    ai_tokens_out  INT UNSIGNED    NULL,
    ai_latency_ms  INT UNSIGNED    NULL,
    created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_messages_external (merchant_id, external_id),
    KEY idx_messages_conv (conversation_id, id),
    CONSTRAINT fk_messages_merchant     FOREIGN KEY (merchant_id)     REFERENCES merchants (id)     ON DELETE CASCADE,
    CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id     BIGINT UNSIGNED NOT NULL,
    contact_id      BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NULL,
    stage           VARCHAR(32)     NOT NULL DEFAULT 'new',
    source          VARCHAR(32)     NULL,
    interest        VARCHAR(255)    NULL,
    score           INT             NOT NULL DEFAULT 0,
    owner_user_id   BIGINT UNSIGNED NULL,
    notes           TEXT            NULL,
    meta            JSON            NULL,
    captured_at     TIMESTAMP       NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_leads_contact (merchant_id, contact_id),
    KEY idx_leads_stage (merchant_id, stage, updated_at),
    CONSTRAINT fk_leads_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE,
    CONSTRAINT fk_leads_contact  FOREIGN KEY (contact_id)  REFERENCES contacts (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
