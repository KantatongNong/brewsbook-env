<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->set404Override(static function () {
    return service('response')->setStatusCode(404)->setJSON([
        'error'   => 'not_found',
        'message' => 'Route not found',
    ]);
});

$routes->get('/', static function () {
    return service('response')->setJSON([
        'service' => 'brewsbook-api',
        'message' => 'Brewsbook Identity & Profile API',
        'health'  => '/health',
    ]);
});

$routes->group('api', ['filter' => 'jwt'], static function ($routes) {
    $routes->get('me', 'MeController::show');
    $routes->put('me', 'MeController::update');
    $routes->post('shop-requests', 'ShopRequestsController::create');
    $routes->get('shop-requests/me', 'ShopRequestsController::mine');

    $routes->group('admin', ['filter' => 'platformAdmin'], static function ($routes) {
        $routes->get('shop-requests', 'AdminShopRequestsController::index');
        $routes->put('shop-requests/(:num)', 'AdminShopRequestsController::decide/$1');
        $routes->put('shops/(:num)/modules', 'AdminShopsController::updateModules/$1');
        $routes->put('shops/(:num)/status', 'AdminShopsController::updateStatus/$1');
        $routes->get('shops', 'AdminShopsController::index');
        $routes->put('shops/(:num)', 'AdminShopsController::update/$1');
        $routes->get('users', 'AdminUsersController::index');
    });
});
