<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Exceptions\ApiException;

class AuthContext
{
    /** @var array<string, mixed>|null */
    private ?array $user = null;

    /** @var list<string> */
    private array $roles = [];

    /**
     * @param array<string, mixed> $user
     * @param list<string>         $roles
     */
    public function set(array $user, array $roles): void
    {
        $this->user  = $user;
        $this->roles = $roles;
    }

    /**
     * @return array<string, mixed>
     */
    public function user(): array
    {
        if ($this->user === null) {
            throw new ApiException(401, 'unauthorized', 'Missing bearer token');
        }

        return $this->user;
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        return $this->roles;
    }

    public function isPlatformAdmin(): bool
    {
        return in_array('platform_admin', $this->roles, true);
    }
}
