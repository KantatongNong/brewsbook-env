<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ApiException;
use CodeIgniter\HTTP\ResponseInterface;

class AdminUsersController extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->respond(function () {
            $query = $this->request->getGet('q');
            if (is_array($query)) {
                throw new ApiException(422, 'validation_failed', 'q must be a string', [
                    'q' => 'Send a single search string',
                ]);
            }
            $query = is_string($query) && trim($query) !== '' ? trim($query) : null;

            return $this->json([
                'data' => service('identityRepository')->listUsers($query),
            ]);
        });
    }
}
