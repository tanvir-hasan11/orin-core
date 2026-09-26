-- Orin Core database schema
-- Storage for provider requests, retry attempts and price-guard events.

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
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cache_key   CHAR(64)        NOT NULL,
    provider    VARCHAR(64)     NOT NULL,
    model       VARCHAR(128)    NOT NULL,
    content     MEDIUMTEXT      NOT NULL,
    hits        INT UNSIGNED    NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_prompt_cache_key (cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
