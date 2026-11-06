<?php

use CodeIgniter\Database\Migration;

class IdentityProfileSchema extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->ensureColumn('users', 'preferred_username', 'preferred_username VARCHAR(255) NULL AFTER email');
        $this->ensureColumn('users', 'given_name', 'given_name VARCHAR(255) NULL AFTER display_name');
        $this->ensureColumn('users', 'family_name', 'family_name VARCHAR(255) NULL AFTER given_name');
        $this->ensureColumn('users', 'phone', 'phone VARCHAR(32) NULL AFTER family_name');
        $this->ensureColumn('users', 'locale', 'locale VARCHAR(16) NULL AFTER phone');
        $this->db->query('ALTER TABLE users MODIFY keycloak_sub VARCHAR(128) NOT NULL');

        $this->ensureColumn('shops', 'description', 'description TEXT NULL AFTER name');
        $this->ensureColumn('shops', 'phone', 'phone VARCHAR(32) NULL AFTER description');
        $this->ensureColumn('shops', 'address_line', 'address_line VARCHAR(500) NULL AFTER phone');
        $this->ensureColumn('shops', 'platform_status', "platform_status VARCHAR(16) NOT NULL DEFAULT 'enabled' AFTER owner_user_id");
        $this->ensureColumn('shops', 'open_status', "open_status VARCHAR(16) NOT NULL DEFAULT 'closed' AFTER platform_status");
        $this->ensureIndex('shops', 'idx_shops_platform_status', 'ALTER TABLE shops ADD KEY idx_shops_platform_status (platform_status)');
        $this->ensureCheck('chk_shops_platform_status', "ALTER TABLE shops ADD CONSTRAINT chk_shops_platform_status CHECK (platform_status IN ('enabled', 'disabled'))");
        $this->ensureCheck('chk_shops_open_status', "ALTER TABLE shops ADD CONSTRAINT chk_shops_open_status CHECK (open_status IN ('open', 'closed'))");

        if ($this->db->fieldExists('status', 'shops')) {
            $this->db->query("UPDATE shops SET platform_status = 'disabled' WHERE status IN ('suspended', 'disabled')");
        }

        $this->ensureColumn('shop_requests', 'shop_name', "shop_name VARCHAR(255) NOT NULL DEFAULT '' AFTER request_type");
        $this->ensureColumn('shop_requests', 'description', 'description TEXT NULL AFTER shop_name');
        $this->ensureColumn('shop_requests', 'phone', 'phone VARCHAR(32) NULL AFTER description');
        $this->ensureColumn('shop_requests', 'address_line', 'address_line VARCHAR(500) NULL AFTER phone');
        $this->ensureColumn('shop_requests', 'rejection_reason', 'rejection_reason VARCHAR(1000) NULL AFTER status');
        $this->ensureColumn('shop_requests', 'approved_modules_json', 'approved_modules_json JSON NULL AFTER rejection_reason');
        $this->ensureColumn('shop_requests', 'reviewed_by', 'reviewed_by BIGINT UNSIGNED NULL AFTER approved_modules_json');
        $this->ensureColumn('shop_requests', 'reviewed_at', 'reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER reviewed_by');
        $this->ensureIndex('shop_requests', 'idx_shop_requests_status', 'ALTER TABLE shop_requests ADD KEY idx_shop_requests_status (status)');
        $this->ensureForeignKey(
            'fk_shop_requests_reviewer',
            'ALTER TABLE shop_requests ADD CONSTRAINT fk_shop_requests_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id)',
        );
        $this->ensurePendingLock();
        $this->ensureCheck('chk_shop_requests_status', "ALTER TABLE shop_requests ADD CONSTRAINT chk_shop_requests_status CHECK (status IN ('pending', 'approved', 'rejected'))");
        $this->ensureCheck('chk_shop_members_role', "ALTER TABLE shop_members ADD CONSTRAINT chk_shop_members_role CHECK (role IN ('owner', 'shop_admin', 'worker'))");
    }

    public function down(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['shop_members', 'shop_modules', 'shop_requests', 'shops', 'users'] as $table) {
            $this->db->query('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function ensurePendingLock(): void
    {
        if (! $this->db->fieldExists('pending_lock', 'shop_requests')) {
            $this->db->query(
                'ALTER TABLE shop_requests ADD COLUMN pending_lock BIGINT UNSIGNED AS (CASE WHEN status = \'pending\' THEN requester_id ELSE NULL END) STORED',
            );
        }
        $this->ensureIndex(
            'shop_requests',
            'uq_shop_requests_one_pending',
            'ALTER TABLE shop_requests ADD UNIQUE KEY uq_shop_requests_one_pending (pending_lock)',
        );
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        if (! $this->db->fieldExists($column, $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD COLUMN ' . $definition);
        }
    }

    private function ensureIndex(string $table, string $index, string $sql): void
    {
        $row = $this->db->query(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$this->db->getDatabase(), $table, $index],
        )->getRowArray();
        if ($row === null) {
            $this->db->query($sql);
        }
    }

    private function ensureForeignKey(string $name, string $sql): void
    {
        $row = $this->db->query(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND CONSTRAINT_NAME = ? LIMIT 1',
            [$this->db->getDatabase(), $name],
        )->getRowArray();
        if ($row === null) {
            $this->db->query($sql);
        }
    }

    private function ensureCheck(string $name, string $sql): void
    {
        $row = $this->db->query(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND CONSTRAINT_NAME = ? LIMIT 1',
            [$this->db->getDatabase(), $name],
        )->getRowArray();
        if ($row === null) {
            $this->db->query($sql);
        }
    }
}
