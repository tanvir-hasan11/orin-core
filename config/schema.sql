-- ============================================================
-- ORIN Core — database schema
-- MySQL 8.0+ / MariaDB 10.6+
-- Single source of truth. Every tenant table carries business_id.
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+06:00';

-- ------------------------------------------------------------
-- Platform
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS businesses (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                VARCHAR(191)        NOT NULL,
    slug                VARCHAR(191)        NOT NULL,
    owner_email         VARCHAR(191)        NOT NULL,
    owner_phone         VARCHAR(32)         NULL,

    -- billing
    subscription_status ENUM('trialing','active','past_due','cancelled') NOT NULL DEFAULT 'trialing',
    trial_ends_at       DATETIME            NULL,
    plan                ENUM('starter','growth','enterprise')               NOT NULL DEFAULT 'starter',
    ai_message_quota    INT UNSIGNED        NOT NULL DEFAULT 1500,
    ai_messages_used    INT UNSIGNED        NOT NULL DEFAULT 0,
    wallet_balance      DECIMAL(12,2)       NOT NULL DEFAULT 0.00,

    -- channels
    wa_phone_number_id  VARCHAR(64)         NULL,
    wa_access_token     TEXT                NULL,   -- encrypted at rest
    messenger_page_id   VARCHAR(64)         NULL,
    meta_page_token     TEXT                NULL,   -- encrypted at rest
    instagram_page_id   VARCHAR(64)         NULL,

    -- behaviour
    autonomy_level      ENUM('off','observe','semi_auto','full_auto') NOT NULL DEFAULT 'semi_auto',
    timezone            VARCHAR(64)         NOT NULL DEFAULT 'Asia/Dhaka',
    send_window_start   TIME                NOT NULL DEFAULT '09:00:00',
    send_window_end     TIME                NOT NULL DEFAULT '22:00:00',

    created_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME            NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_businesses_slug (slug),
    UNIQUE KEY uq_businesses_wa_phone (wa_phone_number_id),
    UNIQUE KEY uq_businesses_messenger_page (messenger_page_id),
    KEY idx_businesses_status (subscription_status, trial_ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Customers & conversations
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS customers (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id   BIGINT UNSIGNED NOT NULL,
    channel       ENUM('whatsapp','messenger','instagram','webchat') NOT NULL,
    external_id   VARCHAR(191)    NOT NULL,   -- phone or page-scoped user id
    display_name  VARCHAR(191)    NULL,
    phone         VARCHAR(32)     NULL,
    email         VARCHAR(191)    NULL,
    address       TEXT            NULL,
    metadata      JSON            NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_external (business_id, channel, external_id),
    KEY idx_customers_phone (business_id, phone),
    CONSTRAINT fk_customers_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversations (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id        BIGINT UNSIGNED NOT NULL,
    customer_id        BIGINT UNSIGNED NOT NULL,
    channel            ENUM('whatsapp','messenger','instagram','webchat') NOT NULL,

    handoff_status     ENUM('none','requested','active','resolved') NOT NULL DEFAULT 'none',
    assigned_user_id   BIGINT UNSIGNED NULL,
    ai_enabled         TINYINT(1)      NOT NULL DEFAULT 1,

    last_message_at    DATETIME        NULL,
    last_customer_at   DATETIME        NULL,
    message_count      INT UNSIGNED    NOT NULL DEFAULT 0,

    created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_conv_business_recent (business_id, last_message_at DESC),
    KEY idx_conv_handoff (business_id, handoff_status),
    CONSTRAINT fk_conv_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_conv_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id     BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NOT NULL,

    external_id     VARCHAR(191)    NULL,      -- provider message id, for dedup
    direction       ENUM('in','out')          NOT NULL,
    sender          ENUM('customer','ai','agent','system') NOT NULL,

    content_type    ENUM('text','image','audio','video','document','location','sticker') NOT NULL DEFAULT 'text',
    body            TEXT            NULL,
    media_url       VARCHAR(512)    NULL,
    raw_payload     JSON            NULL,

    ai_provider     VARCHAR(64)     NULL,
    ai_model        VARCHAR(128)    NULL,
    ai_tokens_in    INT UNSIGNED    NULL,
    ai_tokens_out   INT UNSIGNED    NULL,
    ai_confidence   DECIMAL(4,3)    NULL,

    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_messages_external (business_id, external_id),
    KEY idx_messages_conv (conversation_id, id),
    KEY idx_messages_business_created (business_id, created_at),
    CONSTRAINT fk_messages_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Catalog
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS products (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id   BIGINT UNSIGNED NOT NULL,
    sku           VARCHAR(64)     NULL,
    name          VARCHAR(191)    NOT NULL,
    name_bn       VARCHAR(191)    NULL,
    aliases       JSON            NULL,   -- ['wallet','leather wallet','charm er wallet']
    description   TEXT            NULL,
    price         DECIMAL(10,2)   NOT NULL,
    compare_price DECIMAL(10,2)   NULL,
    stock         INT             NOT NULL DEFAULT 0,
    unit          VARCHAR(32)     NOT NULL DEFAULT 'pcs',
    image_url     VARCHAR(512)    NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_products_business (business_id, is_active),
    FULLTEXT KEY ft_products_search (name, name_bn, description),
    CONSTRAINT fk_products_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS knowledge_documents (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id  BIGINT UNSIGNED NOT NULL,
    title        VARCHAR(191)    NOT NULL,
    body         MEDIUMTEXT      NOT NULL,
    source       VARCHAR(255)    NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kb_business (business_id),
    FULLTEXT KEY ft_kb_search (title, body),
    CONSTRAINT fk_kb_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Orders
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS orders (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id       BIGINT UNSIGNED NOT NULL,
    conversation_id   BIGINT UNSIGNED NULL,
    customer_id       BIGINT UNSIGNED NOT NULL,

    order_number      VARCHAR(32)     NOT NULL,
    status            ENUM('draft','confirmed','packed','shipped','delivered','returned','cancelled') NOT NULL DEFAULT 'draft',

    subtotal          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    delivery_charge   DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    discount          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    total             DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    payment_method    ENUM('cod','bkash','nagad','prepaid') NOT NULL DEFAULT 'cod',

    ship_name         VARCHAR(191)    NULL,
    ship_phone        VARCHAR(32)     NULL,
    ship_address      TEXT            NULL,
    ship_city         VARCHAR(64)     NULL,
    ship_zone         VARCHAR(64)     NULL,
    ship_area         VARCHAR(64)     NULL,

    courier           ENUM('steadfast','pathao','manual') NULL,
    consignment_id    VARCHAR(64)     NULL,
    tracking_code     VARCHAR(64)     NULL,

    notes             TEXT            NULL,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_number (business_id, order_number),
    KEY idx_orders_status (business_id, status, created_at DESC),
    KEY idx_orders_consignment (consignment_id),
    CONSTRAINT fk_orders_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id  BIGINT UNSIGNED NOT NULL,
    order_id     BIGINT UNSIGNED NOT NULL,
    product_id   BIGINT UNSIGNED NULL,
    product_name VARCHAR(191)    NOT NULL,   -- snapshot; product may change later
    unit_price   DECIMAL(10,2)   NOT NULL,
    quantity     INT UNSIGNED    NOT NULL DEFAULT 1,
    line_total   DECIMAL(10,2)   NOT NULL,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Audit — append-only
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS audit_log (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_id  BIGINT UNSIGNED NULL,
    actor_type   ENUM('user','ai','system','webhook') NOT NULL,
    actor_id     VARCHAR(64)     NULL,
    action       VARCHAR(64)     NOT NULL,
    entity_type  VARCHAR(64)     NULL,
    entity_id    VARCHAR(64)     NULL,
    payload      JSON            NULL,
    ip           VARCHAR(45)     NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_business_created (business_id, created_at DESC),
    KEY idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only enforcement: block UPDATE and DELETE.
DROP TRIGGER IF EXISTS trg_audit_no_update;
DROP TRIGGER IF EXISTS trg_audit_no_delete;

DELIMITER //
CREATE TRIGGER trg_audit_no_update BEFORE UPDATE ON audit_log
FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only';
END//
CREATE TRIGGER trg_audit_no_delete BEFORE DELETE ON audit_log
FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only';
END//
DELIMITER ;
