<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class AdminShopRequestsController extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->respond(function () {
            $status = $this->request->getGet('status');
            $status = is_string($status) && $status !== '' ? $status : null;

            return $this->json([
                'data' => service('identityRepository')->listRequests($status),
            ]);
        });
    }

    public function decide(int $id): ResponseInterface
    {
        return $this->respond(function () use ($id) {
            $updated = service('identityRepository')->decideRequest($id, service('authContext')->user(), $this->input());

            return $this->json($updated);
        });
    }
}
