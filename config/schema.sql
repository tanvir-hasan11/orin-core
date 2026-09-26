-- Orin full system schema
-- InnoDB / utf8mb4 throughout. Safe to run repeatedly.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS users (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role              ENUM('merchant','admin','super_admin') NOT NULL DEFAULT 'merchant',
    email             VARCHAR(190)    NOT NULL,
    password_hash     VARCHAR(255)    NOT NULL,
    name              VARCHAR(190)    NOT NULL DEFAULT '',
    status            ENUM('active','pending','suspended') NOT NULL DEFAULT 'active',
    email_verified_at TIMESTAMP       NULL,
    last_login_at     TIMESTAMP       NULL,
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_users_email (email),
    KEY idx_users_role (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plans (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                VARCHAR(120)    NOT NULL,
    code                VARCHAR(64)     NOT NULL,
    price_usd           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    monthly_token_quota BIGINT UNSIGNED NOT NULL DEFAULT 100000,
    max_api_keys        INT UNSIGNED    NOT NULL DEFAULT 3,
    features            JSON            NULL,
    is_active           TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_plans_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchants (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    company_name  VARCHAR(190)    NOT NULL,
    slug          VARCHAR(120)    NOT NULL,
    status        ENUM('trial','active','suspended') NOT NULL DEFAULT 'trial',
    plan_id       BIGINT UNSIGNED NULL,
    trial_ends_at TIMESTAMP       NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_merchants_slug (slug),
    KEY idx_merchants_owner (owner_user_id),
    KEY idx_merchants_status (status),
    CONSTRAINT fk_merchants_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_merchants_plan  FOREIGN KEY (plan_id)       REFERENCES plans (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id BIGINT UNSIGNED NOT NULL,
    plan_id     BIGINT UNSIGNED NOT NULL,
    status      ENUM('active','cancelled','expired') NOT NULL DEFAULT 'active',
    starts_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ends_at     TIMESTAMP       NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_subs_merchant (merchant_id, status),
    CONSTRAINT fk_subs_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE,
    CONSTRAINT fk_subs_plan     FOREIGN KEY (plan_id)     REFERENCES plans (id)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_keys (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id  BIGINT UNSIGNED NOT NULL,
    label        VARCHAR(120)    NOT NULL DEFAULT '',
    key_prefix   VARCHAR(24)     NOT NULL,
    key_hash     CHAR(64)        NOT NULL,
    last_used_at TIMESTAMP       NULL,
    revoked_at   TIMESTAMP       NULL,
    created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_api_keys_hash (key_hash),
    KEY idx_api_keys_prefix (key_prefix),
    KEY idx_api_keys_merchant (merchant_id, revoked_at),
    CONSTRAINT fk_api_keys_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_providers (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id    BIGINT UNSIGNED NOT NULL,
    provider       VARCHAR(64)     NOT NULL,
    enabled        TINYINT(1)      NOT NULL DEFAULT 1,
    default_model  VARCHAR(128)    NOT NULL DEFAULT '',
    has_custom_key TINYINT(1)      NOT NULL DEFAULT 0,
    created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_merchant_provider (merchant_id, provider),
    CONSTRAINT fk_merchant_providers_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_logs (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id       BIGINT UNSIGNED NOT NULL,
    api_key_id        BIGINT UNSIGNED NULL,
    provider          VARCHAR(64)     NOT NULL,
    model             VARCHAR(128)    NOT NULL,
    prompt_tokens     INT UNSIGNED    NOT NULL DEFAULT 0,
    completion_tokens INT UNSIGNED    NOT NULL DEFAULT 0,
    cost_usd          DECIMAL(12,6)   NOT NULL DEFAULT 0,
    latency_ms        INT UNSIGNED    NOT NULL DEFAULT 0,
    status            VARCHAR(24)     NOT NULL DEFAULT 'ok',
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_usage_merchant_time (merchant_id, created_at),
    KEY idx_usage_key (api_key_id),
    CONSTRAINT fk_usage_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE,
    CONSTRAINT fk_usage_key      FOREIGN KEY (api_key_id)  REFERENCES api_keys (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_monthly (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id BIGINT UNSIGNED NOT NULL,
    period      CHAR(7)         NOT NULL,
    tokens_used BIGINT UNSIGNED NOT NULL DEFAULT 0,
    cost_usd    DECIMAL(12,6)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_usage_monthly (merchant_id, period),
    CONSTRAINT fk_usage_monthly_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id BIGINT UNSIGNED NOT NULL,
    period      CHAR(7)         NOT NULL,
    amount_usd  DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    status      ENUM('draft','sent','paid','void') NOT NULL DEFAULT 'draft',
    issued_at   TIMESTAMP       NULL,
    paid_at     TIMESTAMP       NULL,
    pdf_path    VARCHAR(255)    NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_invoice_period (merchant_id, period),
    CONSTRAINT fk_invoices_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id BIGINT UNSIGNED NULL,
    actor_role    VARCHAR(32)     NULL,
    action        VARCHAR(64)     NOT NULL,
    target_type   VARCHAR(64)     NULL,
    target_id     VARCHAR(64)     NULL,
    ip            VARCHAR(45)     NULL,
    user_agent    VARCHAR(255)    NULL,
    meta          JSON            NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_actor (actor_user_id),
    KEY idx_audit_action_time (action, created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    `key`      VARCHAR(120) NOT NULL,
    `value`    TEXT         NULL,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
    id            VARCHAR(128)    NOT NULL,
    user_id       BIGINT UNSIGNED NULL,
    ip            VARCHAR(45)     NULL,
    user_agent    VARCHAR(255)    NULL,
    payload       MEDIUMTEXT      NULL,
    last_activity INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_sessions_user (user_id),
    KEY idx_sessions_activity (last_activity),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    email      VARCHAR(190) NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at TIMESTAMP    NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email, token_hash),
    KEY idx_password_resets_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_requests (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id         CHAR(36)        NOT NULL,
    provider           VARCHAR(64)     NOT NULL,
    model              VARCHAR(128)    NOT NULL,
    status             VARCHAR(16)     NOT NULL,
    prompt_tokens      INT UNSIGNED    NOT NULL DEFAULT 0,
    completion_tokens  INT UNSIGNED    NOT NULL DEFAULT 0,
    latency_ms         INT UNSIGNED    NOT NULL DEFAULT 0,
    cost_usd           DECIMAL(12,6)   NOT NULL DEFAULT 0,
    error_message      TEXT            NULL,
    created_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_provider_requests_request (request_id),
    KEY idx_provider_requests_provider (provider, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_attempts (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id    CHAR(36)        NOT NULL,
    provider      VARCHAR(64)     NOT NULL,
    attempt_no    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    succeeded     TINYINT(1)      NOT NULL DEFAULT 0,
    error_message TEXT            NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_provider_attempts_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS price_guard_events (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id         CHAR(36)        NOT NULL,
    allowed            TINYINT(1)      NOT NULL DEFAULT 1,
    reason             VARCHAR(255)    NULL,
    estimated_input    INT UNSIGNED    NOT NULL DEFAULT 0,
    estimated_output   INT UNSIGNED    NOT NULL DEFAULT 0,
    estimated_cost_usd DECIMAL(12,6)   NOT NULL DEFAULT 0,
    created_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_price_guard_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prompt_cache (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cache_key  CHAR(64)        NOT NULL,
    provider   VARCHAR(64)     NOT NULL,
    model      VARCHAR(128)    NOT NULL,
    content    MEDIUMTEXT      NOT NULL,
    hits       INT UNSIGNED    NOT NULL DEFAULT 0,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_prompt_cache_key (cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO plans (name, code, price_usd, monthly_token_quota, max_api_keys, features, is_active)
VALUES
    ('Free',  'free',  0.00,  100000,   2,  JSON_OBJECT('support','community'), 1),
    ('Pro',   'pro',   29.00, 2000000,  10, JSON_OBJECT('support','email'),     1),
    ('Scale', 'scale', 99.00, 10000000, 50, JSON_OBJECT('support','priority'),  1)
ON DUPLICATE KEY UPDATE name = VALUES(name);
