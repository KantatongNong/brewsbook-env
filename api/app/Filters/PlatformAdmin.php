<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class PlatformAdmin implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! service('authContext')->isPlatformAdmin()) {
            return service('response')->setStatusCode(403)->setJSON([
                'error'   => 'forbidden',
                'message' => 'Platform admin role required',
            ]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
