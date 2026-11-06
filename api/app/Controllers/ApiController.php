<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ApiException;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

abstract class ApiController extends BaseController
{
    /**
     * @param callable(): ResponseInterface $action
     */
    protected function respond(callable $action): ResponseInterface
    {
        try {
            return $action();
        } catch (ApiException $e) {
            return $this->error($e->status, $e->error, $e->getMessage(), $e->fields);
        } catch (Throwable $e) {
            log_message('error', 'Unhandled API error: {message}', ['message' => $e->getMessage()]);
            $message = ENVIRONMENT === 'production' ? 'Internal server error' : $e->getMessage();

            return $this->error(500, 'server_error', $message);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function json(array $data, int $status = 200): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON($data);
    }

    /**
     * @param array<string, string> $fields
     */
    protected function error(int $status, string $error, string $message, array $fields = []): ResponseInterface
    {
        $body = ['error' => $error, 'message' => $message];
        if ($fields !== []) {
            $body['fields'] = $fields;
        }

        return $this->json($body, $status);
    }

    /**
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        $data = $this->request->getJSON(true);
        if (! is_array($data)) {
            throw new ApiException(422, 'validation_failed', 'Request body must be a JSON object');
        }

        return $data;
    }
}
