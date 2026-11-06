<?php

namespace Config;

use App\Libraries\AuthContext;
use App\Libraries\IdentityRepository;
use App\Libraries\JwtAuthenticator;
use App\Libraries\KeycloakAdmin;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function authContext(bool $getShared = true): AuthContext
    {
        if ($getShared) {
            return static::getSharedInstance('authContext');
        }

        return new AuthContext();
    }

    public static function jwtAuthenticator(bool $getShared = true): JwtAuthenticator
    {
        if ($getShared) {
            return static::getSharedInstance('jwtAuthenticator');
        }

        return new JwtAuthenticator();
    }

    public static function keycloakAdmin(bool $getShared = true): KeycloakAdmin
    {
        if ($getShared) {
            return static::getSharedInstance('keycloakAdmin');
        }

        return new KeycloakAdmin();
    }

    public static function identityRepository(bool $getShared = true): IdentityRepository
    {
        if ($getShared) {
            return static::getSharedInstance('identityRepository');
        }

        return new IdentityRepository();
    }
}
