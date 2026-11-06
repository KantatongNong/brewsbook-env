<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Exceptions\ApiException;
use CodeIgniter\Database\BaseConnection;
use Throwable;

class IdentityRepository
{
    /**
     * @param list<string> $roles
     *
     * @return array<string, mixed>
     */
    public function upsertFromClaims(object $claims, array $roles): array
    {
        $sub = (string) $claims->sub;
        $db  = db_connect();
        $row = $db->table('users')->where('keycloak_sub', $sub)->get()->getRowArray();

        $email    = $this->claimString($claims, 'email');
        $username = $this->claimString($claims, 'preferred_username');
        $given    = $this->claimString($claims, 'given_name');
        $family   = $this->claimString($claims, 'family_name');
        $name     = $this->claimString($claims, 'name');
        if ($name === null) {
            $name = trim(($given ?? '') . ' ' . ($family ?? ''));
            $name = $name !== '' ? $name : ($username ?? $email);
        }

        if ($row === null) {
            $insert = [
                'keycloak_sub'       => $sub,
                'email'              => $email,
                'preferred_username' => $username,
                'display_name'       => $name,
                'given_name'         => $given,
                'family_name'        => $family,
            ];
            try {
                $db->table('users')->insert($insert);
            } catch (Throwable $e) {
                $row = $db->table('users')->where('keycloak_sub', $sub)->get()->getRowArray();
                if ($row === null) {
                    throw $e;
                }
            }
            if ($row === null) {
                $row = $db->table('users')->where('keycloak_sub', $sub)->get()->getRowArray();
            }
        } else {
            $update = [];
            if ($email !== null) {
                $update['email'] = $email;
            }
            if ($username !== null) {
                $update['preferred_username'] = $username;
            }
            if (($row['display_name'] === null || $row['display_name'] === '') && $name !== null) {
                $update['display_name'] = $name;
            }
            if (($row['given_name'] === null || $row['given_name'] === '') && $given !== null) {
                $update['given_name'] = $given;
            }
            if (($row['family_name'] === null || $row['family_name'] === '') && $family !== null) {
                $update['family_name'] = $family;
            }
            if ($update !== []) {
                $db->table('users')->where('id', $row['id'])->update($update);
            }
            $row = $db->table('users')->where('id', $row['id'])->get()->getRowArray();
        }

        if ($row === null) {
            throw new ApiException(500, 'server_error', 'Could not store the user profile');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $user
     * @param list<string>         $roles
     *
     * @return array<string, mixed>
     */
    public function presentMe(array $user, array $roles): array
    {
        return $this->presentUser($user, $roles, true);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function updateProfile(array $user, array $input, array $roles): array
    {
        $allowed = ['display_name', 'given_name', 'family_name', 'phone', 'locale'];
        $present = array_values(array_intersect($allowed, array_keys($input)));
        if ($present === []) {
            throw new ApiException(422, 'validation_failed', 'No profile fields to update', [
                'body' => 'Send display_name, given_name, family_name, phone, and/or locale',
            ]);
        }

        $update = [];
        if (array_key_exists('display_name', $input)) {
            $name = $this->requireText($input['display_name'], 'display_name', 1, 255);
            $update['display_name'] = $name;
        }
        if (array_key_exists('given_name', $input)) {
            $update['given_name'] = $this->optionalText($input['given_name'], 'given_name', 255);
        }
        if (array_key_exists('family_name', $input)) {
            $update['family_name'] = $this->optionalText($input['family_name'], 'family_name', 255);
        }
        if (array_key_exists('phone', $input)) {
            $update['phone'] = $this->optionalPhone($input['phone']);
        }
        if (array_key_exists('locale', $input)) {
            $update['locale'] = $this->optionalLocale($input['locale']);
        }

        db_connect()->table('users')->where('id', $user['id'])->update($update);
        $fresh = db_connect()->table('users')->where('id', $user['id'])->get()->getRowArray();
        if ($fresh === null) {
            throw new ApiException(404, 'not_found', 'User not found');
        }

        return $this->presentMe($fresh, $roles);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function createRequest(array $user, array $input): array
    {
        $shopName = $this->requireText($input['shop_name'] ?? null, 'shop_name', 2, 120);
        $description = $this->optionalText($input['description'] ?? null, 'description', 4000);
        $phone = array_key_exists('phone', $input) ? $this->optionalPhone($input['phone']) : null;
        $address = $this->optionalText($input['address_line'] ?? null, 'address_line', 500);

        $db = db_connect();
        $db->transBegin();
        try {
            $pending = $db->query(
                'SELECT id FROM shop_requests WHERE requester_id = ? AND status = ? FOR UPDATE',
                [$user['id'], 'pending'],
            )->getRowArray();
            if ($pending !== null) {
                throw new ApiException(409, 'conflict', 'You already have a pending shop request');
            }

            $payload = [
                'shop_name'    => $shopName,
                'description'  => $description,
                'phone'        => $phone,
                'address_line' => $address,
            ];
            $db->table('shop_requests')->insert([
                'requester_id'  => $user['id'],
                'request_type'  => 'create_shop',
                'shop_name'     => $shopName,
                'description'   => $description,
                'phone'         => $phone,
                'address_line'  => $address,
                'payload_json'  => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'status'        => 'pending',
            ]);
            $id = (int) $db->insertID();
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            if ($this->isDuplicate($e)) {
                throw new ApiException(409, 'conflict', 'You already have a pending shop request');
            }
            throw $e;
        }

        $row = $this->findRequest($id);
        if ($row === null) {
            throw new ApiException(500, 'server_error', 'Could not load the shop request');
        }

        return $this->presentRequest($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMyRequests(int $userId): array
    {
        $rows = db_connect()->query(
            'SELECT r.*, u.email AS requester_email, u.display_name AS requester_display_name, u.preferred_username AS requester_username
             FROM shop_requests r
             JOIN users u ON u.id = r.requester_id
             WHERE r.requester_id = ?
             ORDER BY r.id DESC',
            [$userId],
        )->getResultArray();

        return array_map(fn (array $row) => $this->presentRequest($row), $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRequests(?string $status): array
    {
        if ($status !== null && ! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw new ApiException(422, 'validation_failed', 'status must be pending, approved, or rejected', [
                'status' => 'Use pending, approved, or rejected',
            ]);
        }

        $sql = 'SELECT r.*, u.email AS requester_email, u.display_name AS requester_display_name, u.preferred_username AS requester_username
                FROM shop_requests r
                JOIN users u ON u.id = r.requester_id';
        $params = [];
        if ($status !== null) {
            $sql .= ' WHERE r.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY r.id DESC';
        $rows = db_connect()->query($sql, $params)->getResultArray();

        return array_map(fn (array $row) => $this->presentRequest($row), $rows);
    }

    /**
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function decideRequest(int $id, array $admin, array $input): array
    {
        $action = $input['action'] ?? null;
        if ($action === null && isset($input['status']) && is_string($input['status'])) {
            $action = match ($input['status']) {
                'approved' => 'approve',
                'rejected' => 'reject',
                default    => null,
            };
        }
        if (! in_array($action, ['approve', 'reject'], true)) {
            throw new ApiException(422, 'validation_failed', 'action must be approve or reject', [
                'action' => 'Use approve or reject',
            ]);
        }

        $db = db_connect();
        $db->transBegin();
        try {
            $request = $db->query(
                'SELECT * FROM shop_requests WHERE id = ? FOR UPDATE',
                [$id],
            )->getRowArray();
            if ($request === null) {
                throw new ApiException(404, 'not_found', 'Shop request not found');
            }
            if ($request['status'] !== 'pending') {
                throw new ApiException(409, 'conflict', 'Shop request is already ' . $request['status']);
            }

            if ($action === 'reject') {
                $reason = $this->optionalText($input['rejection_reason'] ?? null, 'rejection_reason', 1000);
                $db->table('shop_requests')->where('id', $id)->update([
                    'status'           => 'rejected',
                    'rejection_reason' => $reason,
                    'reviewed_by'      => $admin['id'],
                    'reviewed_at'      => gmdate('Y-m-d H:i:s'),
                ]);
                $db->transCommit();
            } else {
                $modules = ModuleCatalog::parse($input['enabled_modules'] ?? null);
                $requester = $db->table('users')->where('id', $request['requester_id'])->get()->getRowArray();
                if ($requester === null) {
                    throw new ApiException(500, 'server_error', 'Requester no longer exists');
                }

                $shopId = $this->insertShop($db, $request, (int) $requester['id']);
                $this->insertModules($db, $shopId, $modules);
                $db->table('shop_members')->insert([
                    'shop_id' => $shopId,
                    'user_id' => $requester['id'],
                    'role'    => 'owner',
                ]);
                $db->table('shop_requests')->where('id', $id)->update([
                    'status'                => 'approved',
                    'shop_id'               => $shopId,
                    'approved_modules_json' => json_encode(array_values($modules), JSON_UNESCAPED_UNICODE),
                    'rejection_reason'      => null,
                    'reviewed_by'           => $admin['id'],
                    'reviewed_at'           => gmdate('Y-m-d H:i:s'),
                ]);

                service('keycloakAdmin')->grantRealmRole((string) $requester['keycloak_sub'], 'owner');
                $db->transCommit();
            }
        } catch (Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        $fresh = $this->findRequest($id);
        if ($fresh === null) {
            throw new ApiException(500, 'server_error', 'Could not load the shop request');
        }

        return $this->presentRequest($fresh);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listShops(?string $platformStatus): array
    {
        if ($platformStatus !== null && ! in_array($platformStatus, ['enabled', 'disabled'], true)) {
            throw new ApiException(422, 'validation_failed', 'platform_status must be enabled or disabled', [
                'platform_status' => 'Use enabled or disabled',
            ]);
        }

        $sql = 'SELECT s.*, u.email AS owner_email, u.display_name AS owner_display_name, u.preferred_username AS owner_username
                FROM shops s
                LEFT JOIN users u ON u.id = s.owner_user_id';
        $params = [];
        if ($platformStatus !== null) {
            $sql .= ' WHERE s.platform_status = ?';
            $params[] = $platformStatus;
        }
        $sql .= ' ORDER BY s.id DESC';
        $shops = db_connect()->query($sql, $params)->getResultArray();

        return array_map(fn (array $shop) => $this->presentShop($shop, $this->modulesFor((int) $shop['id'])), $shops);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function updateShop(int $id, array $input): array
    {
        if (array_key_exists('platform_status', $input)) {
            throw new ApiException(422, 'validation_failed', 'Change platform_status with PUT /api/admin/shops/{id}/status', [
                'platform_status' => 'Use the status endpoint',
            ]);
        }

        $shop = $this->requireShop($id);
        $update = [];
        if (array_key_exists('name', $input)) {
            $update['name'] = $this->requireText($input['name'], 'name', 2, 120);
        }
        if (array_key_exists('description', $input)) {
            $update['description'] = $this->optionalText($input['description'], 'description', 4000);
        }
        if (array_key_exists('phone', $input)) {
            $update['phone'] = $this->optionalPhone($input['phone']);
        }
        if (array_key_exists('address_line', $input)) {
            $update['address_line'] = $this->optionalText($input['address_line'], 'address_line', 500);
        }
        if (array_key_exists('open_status', $input)) {
            $update['open_status'] = $this->requireEnum($input['open_status'], 'open_status', ['open', 'closed']);
        }
        if (array_key_exists('slug', $input)) {
            $slug = $this->requireSlug($input['slug']);
            if ($this->slugTaken($slug, $id)) {
                throw new ApiException(409, 'conflict', 'That shop slug is already in use', [
                    'slug' => 'Choose another slug',
                ]);
            }
            $update['slug'] = $slug;
        }

        if ($update === []) {
            throw new ApiException(422, 'validation_failed', 'No shop fields to update', [
                'body' => 'Send name, description, phone, address_line, open_status, and/or slug',
            ]);
        }

        db_connect()->table('shops')->where('id', $id)->update($update);

        return $this->presentShop($this->requireShop($id), $this->modulesFor($id));
    }

    /**
     * @param list<string> $modules
     *
     * @return array<string, mixed>
     */
    public function updateModules(int $id, array $modules): array
    {
        $this->requireShop($id);
        $chosen = array_fill_keys($modules, true);
        $db = db_connect();
        $db->transBegin();
        try {
            foreach (ModuleCatalog::KEYS as $key) {
                $enabled = isset($chosen[$key]) ? 1 : 0;
                $existing = $db->table('shop_modules')->where('shop_id', $id)->where('module_key', $key)->get()->getRowArray();
                if ($existing === null) {
                    $db->table('shop_modules')->insert([
                        'shop_id'    => $id,
                        'module_key' => $key,
                        'enabled'    => $enabled,
                    ]);
                } else {
                    $db->table('shop_modules')->where('id', $existing['id'])->update(['enabled' => $enabled]);
                }
            }
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        return $this->presentShop($this->requireShop($id), $this->modulesFor($id));
    }

    /**
     * @return array<string, mixed>
     */
    public function updatePlatformStatus(int $id, string $status): array
    {
        $shop = $this->requireShop($id);
        $status = $this->requireEnum($status, 'platform_status', ['enabled', 'disabled']);
        db_connect()->table('shops')->where('id', $id)->update(['platform_status' => $status]);
        $fresh = $this->requireShop($id);
        if ($fresh['open_status'] !== $shop['open_status']) {
            throw new ApiException(500, 'server_error', 'Platform status change altered open/closed');
        }

        return $this->presentShop($fresh, $this->modulesFor($id));
    }

    /**
     * @param array<string, mixed> $request
     */
    private function insertShop(BaseConnection $db, array $request, int $ownerId): int
    {
        $base = $this->slugify((string) $request['shop_name']);
        $slug = $base;
        $i = 2;
        while ($this->slugTaken($slug, null)) {
            $suffix = '-' . $i;
            $slug = substr($base, 0, 120 - strlen($suffix)) . $suffix;
            $i++;
            if ($i > 1000) {
                throw new ApiException(409, 'conflict', 'Could not allocate a unique shop slug');
            }
        }

        $db->table('shops')->insert([
            'slug'             => $slug,
            'name'             => $request['shop_name'],
            'description'      => $request['description'],
            'phone'            => $request['phone'],
            'address_line'     => $request['address_line'],
            'owner_user_id'    => $ownerId,
            'platform_status'  => 'enabled',
            'open_status'      => 'closed',
        ]);

        return (int) $db->insertID();
    }

    /**
     * @param list<string> $enabled
     */
    private function insertModules(BaseConnection $db, int $shopId, array $enabled): void
    {
        $chosen = array_fill_keys($enabled, true);
        foreach (ModuleCatalog::KEYS as $key) {
            $db->table('shop_modules')->insert([
                'shop_id'    => $shopId,
                'module_key' => $key,
                'enabled'    => isset($chosen[$key]) ? 1 : 0,
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRequest(int $id): ?array
    {
        return db_connect()->query(
            'SELECT r.*, u.email AS requester_email, u.display_name AS requester_display_name, u.preferred_username AS requester_username
             FROM shop_requests r
             JOIN users u ON u.id = r.requester_id
             WHERE r.id = ?',
            [$id],
        )->getRowArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function requireShop(int $id): array
    {
        $row = db_connect()->query(
            'SELECT s.*, u.email AS owner_email, u.display_name AS owner_display_name, u.preferred_username AS owner_username
             FROM shops s
             LEFT JOIN users u ON u.id = s.owner_user_id
             WHERE s.id = ?',
            [$id],
        )->getRowArray();
        if ($row === null) {
            throw new ApiException(404, 'not_found', 'Shop not found');
        }

        return $row;
    }

    /**
     * @return list<array{module_key: string, enabled: int}>
     */
    private function modulesFor(int $shopId): array
    {
        return db_connect()->table('shop_modules')
            ->select('module_key, enabled')
            ->where('shop_id', $shopId)
            ->orderBy('module_key', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function slugTaken(string $slug, ?int $ignoreId): bool
    {
        $builder = db_connect()->table('shops')->where('slug', $slug);
        if ($ignoreId !== null) {
            $builder->where('id !=', $ignoreId);
        }

        return $builder->countAllResults() > 0;
    }

    private function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'shop';
        }

        return substr($slug, 0, 100);
    }

    /**
     * @param array<string, mixed> $user
     * @param list<string>         $roles
     *
     * @return array<string, mixed>
     */
    private function presentUser(array $user, array $roles, bool $withMemberships): array
    {
        $payload = [
            'id'                  => (int) $user['id'],
            'keycloak_sub'        => $user['keycloak_sub'],
            'email'               => $user['email'],
            'preferred_username'  => $user['preferred_username'],
            'display_name'        => $user['display_name'],
            'given_name'          => $user['given_name'],
            'family_name'         => $user['family_name'],
            'phone'               => $user['phone'],
            'locale'              => $user['locale'],
            'roles'               => $roles,
            'created_at'          => $this->iso($user['created_at'] ?? null),
            'updated_at'          => $this->iso($user['updated_at'] ?? null),
        ];
        if ($withMemberships) {
            $payload['memberships'] = $this->memberships((int) $user['id']);
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function memberships(int $userId): array
    {
        $rows = db_connect()->query(
            'SELECT sm.role, s.id AS shop_id, s.name AS shop_name, s.slug AS shop_slug, s.platform_status, s.open_status
             FROM shop_members sm
             JOIN shops s ON s.id = sm.shop_id
             WHERE sm.user_id = ?
             ORDER BY s.id DESC',
            [$userId],
        )->getResultArray();

        return array_map(static fn (array $row) => [
            'shop_id'          => (int) $row['shop_id'],
            'shop_name'        => $row['shop_name'],
            'shop_slug'        => $row['shop_slug'],
            'role'             => $row['role'],
            'platform_status'  => $row['platform_status'],
            'open_status'      => $row['open_status'],
        ], $rows);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function presentRequest(array $row): array
    {
        $payload = $this->decodeJson($row['payload_json'] ?? null);
        $modules = $this->decodeJson($row['approved_modules_json'] ?? null);

        return [
            'id'               => (int) $row['id'],
            'request_type'     => $row['request_type'],
            'status'           => $row['status'],
            'shop_id'          => $row['shop_id'] !== null ? (int) $row['shop_id'] : null,
            'shop_name'        => $row['shop_name'],
            'description'      => $row['description'],
            'phone'            => $row['phone'],
            'address_line'     => $row['address_line'],
            'payload'          => $payload,
            'rejection_reason' => $row['rejection_reason'],
            'enabled_modules'  => is_array($modules) ? array_values($modules) : null,
            'requester'        => [
                'id'                  => (int) $row['requester_id'],
                'email'               => $row['requester_email'] ?? null,
                'display_name'        => $row['requester_display_name'] ?? null,
                'preferred_username'  => $row['requester_username'] ?? null,
            ],
            'reviewed_by'      => $row['reviewed_by'] !== null ? (int) $row['reviewed_by'] : null,
            'reviewed_at'      => $this->iso($row['reviewed_at'] ?? null),
            'created_at'       => $this->iso($row['created_at'] ?? null),
            'updated_at'       => $this->iso($row['updated_at'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed>                         $shop
     * @param list<array{module_key: string, enabled: int|string}> $modules
     *
     * @return array<string, mixed>
     */
    private function presentShop(array $shop, array $modules): array
    {
        $normalized = array_map(static fn (array $row) => [
            'module_key' => $row['module_key'],
            'enabled'    => (int) $row['enabled'] === 1,
        ], $modules);
        $enabled = [];
        foreach ($normalized as $row) {
            if ($row['enabled']) {
                $enabled[] = $row['module_key'];
            }
        }

        return [
            'id'               => (int) $shop['id'],
            'slug'             => $shop['slug'],
            'name'             => $shop['name'],
            'description'      => $shop['description'],
            'phone'            => $shop['phone'],
            'address_line'     => $shop['address_line'],
            'owner_user_id'    => $shop['owner_user_id'] !== null ? (int) $shop['owner_user_id'] : null,
            'owner'            => $shop['owner_user_id'] === null ? null : [
                'id'                 => (int) $shop['owner_user_id'],
                'email'              => $shop['owner_email'] ?? null,
                'display_name'       => $shop['owner_display_name'] ?? null,
                'preferred_username' => $shop['owner_username'] ?? null,
            ],
            'platform_status'  => $shop['platform_status'],
            'open_status'      => $shop['open_status'],
            'modules'          => $normalized,
            'enabled_modules'  => $enabled,
            'created_at'       => $this->iso($shop['created_at'] ?? null),
            'updated_at'       => $this->iso($shop['updated_at'] ?? null),
        ];
    }

    private function isDuplicate(Throwable $e): bool
    {
        $message = $e->getMessage();
        $previous = $e->getPrevious();
        if ($previous !== null) {
            $message .= ' ' . $previous->getMessage();
        }

        return str_contains($message, '1062') || str_contains($message, 'Duplicate');
    }

    private function claimString(object $claims, string $key): ?string
    {
        if (! isset($claims->{$key}) || ! is_string($claims->{$key})) {
            return null;
        }
        $value = trim($claims->{$key});

        return $value === '' ? null : $value;
    }

    private function requireText(mixed $value, string $field, int $min, int $max): string
    {
        if (! is_string($value)) {
            throw new ApiException(422, 'validation_failed', $field . ' is required', [
                $field => 'Enter a value',
            ]);
        }
        $value = trim($value);
        $len = mb_strlen($value);
        if ($len < $min || $len > $max) {
            throw new ApiException(422, 'validation_failed', $field . ' must be ' . $min . '–' . $max . ' characters', [
                $field => 'Use ' . $min . '–' . $max . ' characters',
            ]);
        }

        return $value;
    }

    private function optionalText(mixed $value, string $field, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new ApiException(422, 'validation_failed', $field . ' must be a string', [
                $field => 'Must be a string',
            ]);
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw new ApiException(422, 'validation_failed', $field . ' is too long', [
                $field => 'Max ' . $max . ' characters',
            ]);
        }

        return $value;
    }

    private function optionalPhone(mixed $value): ?string
    {
        $phone = $this->optionalText($value, 'phone', 32);
        if ($phone === null) {
            return null;
        }
        if (! preg_match('/^[0-9+().\\-\\s]{3,32}$/', $phone)) {
            throw new ApiException(422, 'validation_failed', 'phone has an invalid format', [
                'phone' => 'Use digits, spaces, and + ( ) . -',
            ]);
        }

        return $phone;
    }

    private function optionalLocale(mixed $value): ?string
    {
        $locale = $this->optionalText($value, 'locale', 16);
        if ($locale === null) {
            return null;
        }
        if (! preg_match('/^[a-zA-Z]{2,3}(?:[-_][a-zA-Z0-9]{2,8})?$/', $locale)) {
            throw new ApiException(422, 'validation_failed', 'locale has an invalid format', [
                'locale' => 'Use a code such as en or th',
            ]);
        }

        return $locale;
    }

    /**
     * @param list<string> $allowed
     */
    private function requireEnum(mixed $value, string $field, array $allowed): string
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new ApiException(422, 'validation_failed', $field . ' must be ' . implode(' or ', $allowed), [
                $field => 'Use ' . implode(' or ', $allowed),
            ]);
        }

        return $value;
    }

    private function requireSlug(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) || strlen($value) > 120) {
            throw new ApiException(422, 'validation_failed', 'slug must be lowercase letters, numbers, and hyphens', [
                'slug' => 'Use a lowercase slug',
            ]);
        }

        return $value;
    }

    private function decodeJson(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value)) {
            return null;
        }
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function iso(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        if ($dt === false) {
            return $value;
        }

        return $dt->format('Y-m-d\TH:i:s\Z');
    }
}
