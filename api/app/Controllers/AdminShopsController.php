<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\ModuleCatalog;
use CodeIgniter\HTTP\ResponseInterface;

class AdminShopsController extends ApiController
{
    public function index(): ResponseInterface
    {
        return $this->respond(function () {
            $status = $this->request->getGet('platform_status');
            $status = is_string($status) && $status !== '' ? $status : null;

            return $this->json([
                'data' => service('identityRepository')->listShops($status),
            ]);
        });
    }

    public function update(int $id): ResponseInterface
    {
        return $this->respond(function () use ($id) {
            return $this->json(service('identityRepository')->updateShop($id, $this->input()));
        });
    }

    public function updateModules(int $id): ResponseInterface
    {
        return $this->respond(function () use ($id) {
            $input = $this->input();
            $modules = ModuleCatalog::parse($input['enabled_modules'] ?? null);

            return $this->json(service('identityRepository')->updateModules($id, $modules));
        });
    }

    public function updateStatus(int $id): ResponseInterface
    {
        return $this->respond(function () use ($id) {
            $input = $this->input();
            $value = $input['platform_status'] ?? null;
            if (! is_string($value)) {
                return $this->error(422, 'validation_failed', 'platform_status is required', [
                    'platform_status' => 'Use enabled or disabled',
                ]);
            }

            return $this->json(service('identityRepository')->updatePlatformStatus($id, $value));
        });
    }
}
