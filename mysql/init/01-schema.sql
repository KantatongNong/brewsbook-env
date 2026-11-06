-- Brewsbook local schema stubs (lean).
-- Real migrations (CodeIgniter / Flyway / etc.) will own this later.
-- Entity names align with BA ER: users mapping, shops, shop_requests, shop_modules, shop_members.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS brewsbook
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE brewsbook;

-- Maps app user to Keycloak subject (sub). Profile fields TBD.
CREATE TABLE IF NOT EXISTS users (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  keycloak_sub  VARCHAR(64) NOT NULL,
  email         VARCHAR(255) NULL,
  display_name  VARCHAR(255) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_keycloak_sub (keycloak_sub),
  KEY idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shop Page / Store core
CREATE TABLE IF NOT EXISTS shops (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug          VARCHAR(120) NOT NULL,
  name          VARCHAR(255) NOT NULL,
  owner_user_id BIGINT UNSIGNED NULL,
  status        VARCHAR(32) NOT NULL DEFAULT 'draft', -- draft|pending|active|suspended
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shops_slug (slug),
  KEY idx_shops_owner (owner_user_id),
  CONSTRAINT fk_shops_owner FOREIGN KEY (owner_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Owner applies to open / enable a shop
CREATE TABLE IF NOT EXISTS shop_requests (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shop_id       BIGINT UNSIGNED NULL,
  requester_id  BIGINT UNSIGNED NOT NULL,
  request_type  VARCHAR(64) NOT NULL, -- create_shop|enable_module|...
  status        VARCHAR(32) NOT NULL DEFAULT 'pending', -- pending|approved|rejected
  payload_json  JSON NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_shop_requests_shop (shop_id),
  KEY idx_shop_requests_requester (requester_id),
  CONSTRAINT fk_shop_requests_shop FOREIGN KEY (shop_id) REFERENCES shops (id),
  CONSTRAINT fk_shop_requests_requester FOREIGN KEY (requester_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modules: Shop Page, Shop Store, Location, Ecommerce, Rating, Beer Keg
CREATE TABLE IF NOT EXISTS shop_modules (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shop_id       BIGINT UNSIGNED NOT NULL,
  module_key    VARCHAR(64) NOT NULL, -- shop_page|shop_store|location|ecommerce|rating|beer_keg
  enabled       TINYINT(1) NOT NULL DEFAULT 0,
  config_json   JSON NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shop_modules (shop_id, module_key),
  CONSTRAINT fk_shop_modules_shop FOREIGN KEY (shop_id) REFERENCES shops (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Membership: owner / shop_admin / worker (app-side; Keycloak roles are source TBD — OQ-14)
CREATE TABLE IF NOT EXISTS shop_members (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shop_id       BIGINT UNSIGNED NOT NULL,
  user_id       BIGINT UNSIGNED NOT NULL,
  role          VARCHAR(32) NOT NULL, -- owner|shop_admin|worker
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shop_members (shop_id, user_id),
  KEY idx_shop_members_user (user_id),
  CONSTRAINT fk_shop_members_shop FOREIGN KEY (shop_id) REFERENCES shops (id),
  CONSTRAINT fk_shop_members_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
