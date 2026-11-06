<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class MeController extends ApiController
{
    public function show(): ResponseInterface
    {
        return $this->respond(function () {
            $auth = service('authContext');

            return $this->json(service('identityRepository')->presentMe($auth->user(), $auth->roles()));
        });
    }

    public function update(): ResponseInterface
    {
        return $this->respond(function () {
            $auth = service('authContext');
            $me = service('identityRepository')->updateProfile($auth->user(), $this->input(), $auth->roles());

            return $this->json($me);
        });
    }
}
