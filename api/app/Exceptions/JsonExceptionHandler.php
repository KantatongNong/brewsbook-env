<?php

declare(strict_types=1);

namespace App\Exceptions;

use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class JsonExceptionHandler extends BaseExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        if (! $request instanceof IncomingRequest) {
            $this->render($exception, $statusCode);
            if (ENVIRONMENT !== 'testing') {
                exit($exitCode);
            }

            return;
        }

        $safeMessage = $statusCode >= 500 && ENVIRONMENT === 'production'
            ? 'Internal server error'
            : $exception->getMessage();

        if ($safeMessage === '') {
            $safeMessage = 'Request failed';
        }

        $error = match (true) {
            $statusCode === 404 => 'not_found',
            $statusCode === 401 => 'unauthorized',
            $statusCode === 403 => 'forbidden',
            $statusCode === 422 => 'validation_failed',
            $statusCode >= 500  => 'server_error',
            default             => 'error',
        };

        log_message('error', '[{status}] {message}', [
            'status'  => $statusCode,
            'message' => $exception->getMessage(),
        ]);

        $response->setStatusCode($statusCode);
        $response->setJSON([
            'error'   => $error,
            'message' => $safeMessage,
        ]);
        $response->send();

        if (ENVIRONMENT !== 'testing') {
            exit($exitCode);
        }
    }
}
