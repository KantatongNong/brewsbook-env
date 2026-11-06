<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class ShopRequestsController extends ApiController
{
    public function create(): ResponseInterface
    {
        return $this->respond(function () {
            $created = service('identityRepository')->createRequest(service('authContext')->user(), $this->input());

            return $this->json($created, 201);
        });
    }

    public function mine(): ResponseInterface
    {
        return $this->respond(function () {
            $user = service('authContext')->user();

            return $this->json([
                'data' => service('identityRepository')->listMyRequests((int) $user['id']),
            ]);
        });
    }
}
