<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Exceptions\ApiException;

class KeycloakAdmin
{
    public function grantRealmRole(string $userId, string $roleName): void
    {
        $token = $this->adminToken();
        $role  = $this->roleRepresentation($token, $roleName);
        $url   = $this->adminUrl('/users/' . rawurlencode($userId) . '/role-mappings/realm');
        $res   = $this->json($token, 'POST', $url, [$role]);

        if (in_array($res['status'], [200, 201, 204], true)) {
            return;
        }

        log_message('error', 'Keycloak role grant failed HTTP {status} for user {user}', [
            'status' => $res['status'],
            'user'   => $userId,
        ]);

        if ($res['status'] === 404) {
            throw new ApiException(502, 'bad_gateway', 'Keycloak user for this requester was not found');
        }

        throw new ApiException(502, 'bad_gateway', 'Could not grant the owner role in Keycloak');
    }

    private function adminToken(): string
    {
        $username = $this->env('KEYCLOAK_ADMIN', 'admin');
        $password = $this->env('KEYCLOAK_ADMIN_PASSWORD', '');
        if ($password === '') {
            throw new ApiException(502, 'bad_gateway', 'Keycloak admin credentials are not configured');
        }

        $url = $this->base() . '/realms/master/protocol/openid-connect/token';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'password',
                'client_id'  => 'admin-cli',
                'username'   => $username,
                'password'   => $password,
            ]),
        ]);
        $raw    = curl_exec($ch);
        $errno  = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($errno !== 0 || $status !== 200 || ! is_array($decoded) || ! isset($decoded['access_token'])) {
            log_message('error', 'Keycloak admin token request failed HTTP {status}', ['status' => $status]);
            throw new ApiException(502, 'bad_gateway', 'Could not sign in to the Keycloak admin API');
        }

        return (string) $decoded['access_token'];
    }

    /**
     * @return array{id: string, name: string}
     */
    private function roleRepresentation(string $token, string $roleName): array
    {
        $url = $this->adminUrl('/roles/' . rawurlencode($roleName));
        $res = $this->json($token, 'GET', $url, null);
        $body = $res['body'];
        if ($res['status'] !== 200 || ! is_array($body) || ! isset($body['id'], $body['name'])) {
            log_message('error', 'Keycloak role lookup failed HTTP {status} for {role}', [
                'status' => $res['status'],
                'role'   => $roleName,
            ]);
            throw new ApiException(502, 'bad_gateway', 'Could not read the owner role from Keycloak');
        }

        return ['id' => (string) $body['id'], 'name' => (string) $body['name']];
    }

    /**
     * @param array<mixed>|null $json
     *
     * @return array{status: int, body: mixed}
     */
    private function json(string $token, string $method, string $url, ?array $json): array
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ];
        $body = null;
        if ($json !== null) {
            $body = json_encode($json, JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $body,
        ]);
        $raw    = curl_exec($ch);
        $errno  = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new ApiException(502, 'bad_gateway', 'Keycloak is unreachable');
        }

        $decoded = null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
        }

        return ['status' => $status, 'body' => $decoded];
    }

    private function adminUrl(string $path): string
    {
        $realm = $this->env('KEYCLOAK_REALM', 'brewsbook');

        return $this->base() . '/admin/realms/' . rawurlencode($realm) . $path;
    }

    private function base(): string
    {
        return rtrim($this->env('KEYCLOAK_INTERNAL_URL', 'http://keycloak:8080'), '/');
    }

    private function env(string $key, string $default): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }
}
