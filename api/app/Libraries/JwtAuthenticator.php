<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Exceptions\ApiException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use UnexpectedValueException;

class JwtAuthenticator
{
    public function __construct()
    {
        JWT::$leeway = 10;
    }

    /**
     * @return array{sub: string, claims: object, roles: list<string>}
     */
    public function authenticate(string $token): array
    {
        try {
            $claims = $this->decode($token, false);
        } catch (ExpiredException $e) {
            throw $e;
        } catch (UnexpectedValueException $e) {
            if (! str_contains(strtolower($e->getMessage()), 'kid')) {
                throw $e;
            }
            $claims = $this->decode($token, true);
        }

        $issuer = rtrim($this->env('OIDC_ISSUER', 'http://localhost:8081/realms/brewsbook'), '/');
        $tokenIssuer = isset($claims->iss) ? rtrim((string) $claims->iss, '/') : '';
        if ($tokenIssuer === '' || $tokenIssuer !== $issuer) {
            throw new ApiException(401, 'unauthorized', 'Invalid bearer token');
        }

        if (! isset($claims->sub) || ! is_string($claims->sub) || $claims->sub === '') {
            throw new ApiException(401, 'unauthorized', 'Invalid bearer token');
        }

        if (isset($claims->azp)) {
            $allowed = $this->allowedAzp();
            if (! in_array((string) $claims->azp, $allowed, true)) {
                throw new ApiException(401, 'unauthorized', 'Invalid bearer token');
            }
        }

        $roles = [];
        if (isset($claims->realm_access->roles) && is_array($claims->realm_access->roles)) {
            $roles = ModuleCatalog::visibleRoles($claims->realm_access->roles);
        }

        return [
            'sub'    => $claims->sub,
            'claims' => $claims,
            'roles'  => $roles,
        ];
    }

    private function decode(string $token, bool $refresh): object
    {
        $jwks = $this->jwks($refresh);
        $keys = JWK::parseKeySet($jwks);

        return JWT::decode($token, $keys);
    }

    /**
     * @return array<string, mixed>
     */
    private function jwks(bool $force): array
    {
        $path = WRITEPATH . 'cache/oidc-jwks.json';
        if (! $force && is_file($path)) {
            $cached = json_decode((string) file_get_contents($path), true);
            if (
                is_array($cached)
                && isset($cached['fetched_at'], $cached['jwks']['keys'])
                && is_array($cached['jwks'])
                && (int) $cached['fetched_at'] > time() - 600
            ) {
                return $cached['jwks'];
            }
        }

        try {
            $jwks = $this->fetchJwks();
        } catch (ApiException $e) {
            if (is_file($path)) {
                $cached = json_decode((string) file_get_contents($path), true);
                if (is_array($cached) && isset($cached['jwks']['keys'])) {
                    log_message('warning', 'Using stale JWKS cache: {message}', ['message' => $e->getMessage()]);

                    return $cached['jwks'];
                }
            }

            throw $e;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, json_encode(['fetched_at' => time(), 'jwks' => $jwks]));

        return $jwks;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchJwks(): array
    {
        $url = $this->env('OIDC_JWKS_URL', '');
        if ($url === '') {
            $base  = rtrim($this->env('KEYCLOAK_INTERNAL_URL', 'http://keycloak:8080'), '/');
            $realm = $this->env('KEYCLOAK_REALM', 'brewsbook');
            $url   = $base . '/realms/' . rawurlencode($realm) . '/protocol/openid-connect/certs';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $raw   = curl_exec($ch);
        $errno = curl_errno($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno !== 0 || ! is_string($raw) || $code !== 200) {
            log_message('error', 'JWKS fetch failed from {url} HTTP {code}', ['url' => $url, 'code' => $code]);
            throw new ApiException(503, 'unavailable', 'Token keys are temporarily unavailable');
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! isset($decoded['keys']) || ! is_array($decoded['keys'])) {
            throw new ApiException(503, 'unavailable', 'Token keys are temporarily unavailable');
        }

        return $decoded;
    }

    /**
     * @return list<string>
     */
    private function allowedAzp(): array
    {
        $raw = $this->env('OIDC_ALLOWED_AZP', 'brewsbook-nextjs,brewsbook-api');
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn ($v) => $v !== ''));

        return $parts === [] ? ['brewsbook-nextjs', 'brewsbook-api'] : $parts;
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
