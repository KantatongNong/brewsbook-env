-- Brewsbook Identity & Profile schema.
-- Fresh volumes apply this file. Existing volumes are upgraded by the
-- CodeIgniter migration IdentityProfileSchema (php spark migrate).

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS brewsbook
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE brewsbook;

-- App user mapped to a Keycloak subject (sub). Platform roles live in Keycloak.
CREATE TABLE IF NOT EXISTS users (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  keycloak_sub        VARCHAR(128) NOT NULL,
  email               VARCHAR(255) NULL,
  preferred_username  VARCHAR(255) NULL,
  display_name        VARCHAR(255) NULL,
  given_name          VARCHAR(255) NULL,
  family_name         VARCHAR(255) NULL,
  phone               VARCHAR(32) NULL,
  locale              VARCHAR(16) NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_keycloak_sub (keycloak_sub),
  KEY idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shop created 1:1 when a shop request is approved.
-- platform_status: platform gate (enabled|disabled).
-- open_status: shop's own sign (open|closed). These are independent.
CREATE TABLE IF NOT EXISTS shops (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug             VARCHAR(120) NOT NULL,
  name             VARCHAR(255) NOT NULL,
  description      TEXT NULL,
  phone            VARCHAR(32) NULL,
  address_line     VARCHAR(500) NULL,
  owner_user_id    BIGINT UNSIGNED NULL,
  platform_status  VARCHAR(16) NOT NULL DEFAULT 'enabled',
  open_status      VARCHAR(16) NOT NULL DEFAULT 'closed',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shops_slug (slug),
  KEY idx_shops_owner (owner_user_id),
  KEY idx_shops_platform_status (platform_status),
  CONSTRAINT fk_shops_owner FOREIGN KEY (owner_user_id) REFERENCES users (id),
  CONSTRAINT chk_shops_platform_status CHECK (platform_status IN ('enabled', 'disabled')),
  CONSTRAINT chk_shops_open_status CHECK (open_status IN ('open', 'closed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Buyer request to open a shop. status: pending|approved|rejected.
CREATE TABLE IF NOT EXISTS shop_requests (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shop_id                BIGINT UNSIGNED NULL,
  requester_id           BIGINT UNSIGNED NOT NULL,
  request_type           VARCHAR(64) NOT NULL DEFAULT 'create_shop',
  shop_name              VARCHAR(255) NOT NULL DEFAULT '',
  description            TEXT NULL,
  phone                  VARCHAR(32) NULL,
  address_line           VARCHAR(500) NULL,
  payload_json           JSON NULL,
  status                 VARCHAR(32) NOT NULL DEFAULT 'pending',
  rejection_reason       VARCHAR(1000) NULL,
  approved_modules_json  JSON NULL,
  reviewed_by            BIGINT UNSIGNED NULL,
  reviewed_at            TIMESTAMP NULL DEFAULT NULL,
  created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  pending_lock           BIGINT UNSIGNED
    AS (CASE WHEN status = 'pending' THEN requester_id ELSE NULL END) STORED,
  PRIMARY KEY (id),
  KEY idx_shop_requests_shop (shop_id),
  KEY idx_shop_requests_requester (requester_id),
  KEY idx_shop_requests_status (status),
  UNIQUE KEY uq_shop_requests_one_pending (pending_lock),
  CONSTRAINT fk_shop_requests_shop FOREIGN KEY (shop_id) REFERENCES shops (id),
  CONSTRAINT fk_shop_requests_requester FOREIGN KEY (requester_id) REFERENCES users (id),
  CONSTRAINT fk_shop_requests_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id),
  CONSTRAINT chk_shop_requests_status CHECK (status IN ('pending', 'approved', 'rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Module flags chosen by a platform admin.
-- module_key: ecommerce | shop_store | location | beer_keg
CREATE TABLE IF NOT EXISTS shop_modules (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shop_id      BIGINT UNSIGNED NOT NULL,
  module_key   VARCHAR(64) NOT NULL,
  enabled      TINYINT(1) NOT NULL DEFAULT 0,
  config_json  JSON NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shop_modules (shop_id, module_key),
  CONSTRAINT fk_shop_modules_shop FOREIGN KEY (shop_id) REFERENCES shops (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shop membership. role: owner | shop_admin | worker.
-- This slice grants owner on approval. Invites are out of scope.
CREATE TABLE IF NOT EXISTS shop_members (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shop_id     BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  role        VARCHAR(32) NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shop_members (shop_id, user_id),
  KEY idx_shop_members_user (user_id),
  CONSTRAINT fk_shop_members_shop FOREIGN KEY (shop_id) REFERENCES shops (id),
  CONSTRAINT fk_shop_members_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT chk_shop_members_role CHECK (role IN ('owner', 'shop_admin', 'worker'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
