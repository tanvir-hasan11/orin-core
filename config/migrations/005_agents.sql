-- Phase 5: per-merchant AI agents, their knowledge, skills, actions, follow-ups.
-- Idempotent. Safe to re-run.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS agents (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id         BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(120)    NOT NULL,
    role_label          VARCHAR(120)    NOT NULL DEFAULT 'Sales Assistant',
    is_default          TINYINT(1)      NOT NULL DEFAULT 0,
    status              ENUM('active','paused') NOT NULL DEFAULT 'active',
    autonomy            ENUM('off','observe','semi_auto','full_auto') NOT NULL DEFAULT 'semi_auto',
    tone                VARCHAR(32)     NOT NULL DEFAULT 'friendly',
    language            VARCHAR(16)     NOT NULL DEFAULT 'auto',
    system_instructions TEXT            NULL,
    escalation_rules    TEXT            NULL,
    after_hours_message VARCHAR(500)    NULL,
    max_turns           INT UNSIGNED    NOT NULL DEFAULT 40,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_agents_name (merchant_id, name),
    KEY idx_agents_default (merchant_id, is_default, status),
    CONSTRAINT fk_agents_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_knowledge (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id BIGINT UNSIGNED NOT NULL,
    agent_id    BIGINT UNSIGNED NULL,
    kind        ENUM('about','faq','policy','shipping','payment','product_info','custom') NOT NULL DEFAULT 'faq',
    title       VARCHAR(190)    NOT NULL,
    body        MEDIUMTEXT      NOT NULL,
    keywords    VARCHAR(500)    NULL,
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kb_merchant (merchant_id, is_active),
    KEY idx_kb_agent (agent_id, is_active),
    FULLTEXT KEY ft_kb_search (title, body, keywords),
    CONSTRAINT fk_kb_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE,
    CONSTRAINT fk_kb_agent    FOREIGN KEY (agent_id)    REFERENCES agents (id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_skills (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id BIGINT UNSIGNED NOT NULL,
    agent_id    BIGINT UNSIGNED NOT NULL,
    skill       VARCHAR(64)     NOT NULL,
    enabled     TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_agent_skill (agent_id, skill),
    CONSTRAINT fk_agent_skills_agent    FOREIGN KEY (agent_id)    REFERENCES agents (id)    ON DELETE CASCADE,
    CONSTRAINT fk_agent_skills_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_runs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id     BIGINT UNSIGNED NOT NULL,
    agent_id        BIGINT UNSIGNED NULL,
    conversation_id BIGINT UNSIGNED NULL,
    decision        VARCHAR(32)     NOT NULL,
    directive       JSON            NULL,
    tokens_in       INT UNSIGNED    NOT NULL DEFAULT 0,
    tokens_out      INT UNSIGNED    NOT NULL DEFAULT 0,
    latency_ms      INT UNSIGNED    NOT NULL DEFAULT 0,
    note            VARCHAR(500)    NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_runs_merchant (merchant_id, created_at),
    KEY idx_runs_agent (agent_id, created_at),
    CONSTRAINT fk_runs_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE,
    CONSTRAINT fk_runs_agent    FOREIGN KEY (agent_id)    REFERENCES agents (id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_actions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id     BIGINT UNSIGNED NOT NULL,
    agent_id        BIGINT UNSIGNED NULL,
    conversation_id BIGINT UNSIGNED NULL,
    contact_id      BIGINT UNSIGNED NULL,
    kind            VARCHAR(24)     NOT NULL,
    status          ENUM('pending','confirmed','cancelled','done') NOT NULL DEFAULT 'pending',
    title           VARCHAR(190)    NOT NULL DEFAULT '',
    payload         JSON            NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_actions_merchant (merchant_id, kind, status),
    CONSTRAINT fk_actions_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE,
    CONSTRAINT fk_actions_agent    FOREIGN KEY (agent_id)    REFERENCES agents (id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS followups (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merchant_id     BIGINT UNSIGNED NOT NULL,
    agent_id        BIGINT UNSIGNED NULL,
    conversation_id BIGINT UNSIGNED NULL,
    contact_id      BIGINT UNSIGNED NOT NULL,
    reason          VARCHAR(255)    NOT NULL DEFAULT '',
    due_at          DATETIME        NOT NULL,
    status          ENUM('pending','sent','cancelled','failed') NOT NULL DEFAULT 'pending',
    sent_at         TIMESTAMP       NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_followups_due (status, due_at),
    KEY idx_followups_merchant (merchant_id, status),
    CONSTRAINT fk_followups_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE,
    CONSTRAINT fk_followups_contact  FOREIGN KEY (contact_id)  REFERENCES contacts (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- messages gains agent_id and status. MySQL 8 has no ADD COLUMN IF NOT EXISTS,
-- so check information_schema first.
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'messages' AND column_name = 'agent_id');
SET @s := IF(@c = 0, 'ALTER TABLE messages ADD COLUMN agent_id BIGINT UNSIGNED NULL AFTER conversation_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'messages' AND column_name = 'status');
SET @s := IF(@c = 0, "ALTER TABLE messages ADD COLUMN status ENUM('sent','draft','failed') NOT NULL DEFAULT 'sent' AFTER ai_latency_ms", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- plans gains a per-plan agent allowance.
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'plans' AND column_name = 'max_agents');
SET @s := IF(@c = 0, 'ALTER TABLE plans ADD COLUMN max_agents INT UNSIGNED NOT NULL DEFAULT 1 AFTER max_api_keys', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
