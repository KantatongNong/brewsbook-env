<?php

declare(strict_types=1);

namespace App\Filters;

use App\Exceptions\ApiException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\ExpiredException;
use Throwable;

class JwtAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $this->authorizationHeader($request);
        if (! preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return $this->error(401, 'unauthorized', 'Missing bearer token');
        }

        try {
            $auth = service('jwtAuthenticator')->authenticate($matches[1]);
        } catch (ExpiredException) {
            return $this->error(401, 'unauthorized', 'Token expired');
        } catch (ApiException $e) {
            return $this->error($e->status, $e->error, $e->getMessage());
        } catch (Throwable $e) {
            log_message('info', 'Rejected bearer token: {message}', ['message' => $e->getMessage()]);

            return $this->error(401, 'unauthorized', 'Invalid bearer token');
        }

        try {
            $user = service('identityRepository')->upsertFromClaims($auth['claims'], $auth['roles']);
            service('authContext')->set($user, $auth['roles']);
        } catch (ApiException $e) {
            return $this->error($e->status, $e->error, $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'User provisioning failed: {message}', ['message' => $e->getMessage()]);

            return $this->error(500, 'server_error', 'Could not load the user profile');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function authorizationHeader(RequestInterface $request): string
    {
        $header = $request->getHeaderLine('Authorization');
        if ($header !== '') {
            return $header;
        }
        if (! empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return (string) $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (! empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    return (string) $value;
                }
            }
        }

        return '';
    }

    private function error(int $status, string $error, string $message): ResponseInterface
    {
        return service('response')->setStatusCode($status)->setJSON([
            'error'   => $error,
            'message' => $message,
        ]);
    }
}
