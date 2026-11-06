<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Exceptions\ApiException;

final class ModuleCatalog
{
    public const KEYS = ['ecommerce', 'shop_store', 'location', 'beer_keg'];

    /**
     * @return list<string>
     */
    public static function parse(mixed $input): array
    {
        if (! is_array($input) || $input === []) {
            throw new ApiException(
                422,
                'validation_failed',
                'enabled_modules must include at least one of: ' . implode(', ', self::KEYS),
                ['enabled_modules' => 'Choose at least one module'],
            );
        }

        $chosen = [];
        foreach ($input as $key) {
            if (! is_string($key)) {
                throw new ApiException(422, 'validation_failed', 'enabled_modules must be an array of module keys', [
                    'enabled_modules' => 'Each module must be a string',
                ]);
            }
            $key = strtolower(trim($key));
            if (! in_array($key, self::KEYS, true)) {
                throw new ApiException(422, 'validation_failed', 'Unknown module "' . $key . '"', [
                    'enabled_modules' => 'Allowed modules: ' . implode(', ', self::KEYS),
                ]);
            }
            $chosen[$key] = true;
        }

        if ($chosen === []) {
            throw new ApiException(422, 'validation_failed', 'enabled_modules must include at least one module', [
                'enabled_modules' => 'Choose at least one module',
            ]);
        }

        return array_keys($chosen);
    }

    /**
     * @param list<string> $roles
     *
     * @return list<string>
     */
    public static function visibleRoles(array $roles): array
    {
        $hidden = ['offline_access', 'uma_authorization'];
        $out    = [];
        foreach ($roles as $role) {
            if (! is_string($role) || $role === '') {
                continue;
            }
            if (in_array($role, $hidden, true) || str_starts_with($role, 'default-roles-')) {
                continue;
            }
            $out[$role] = true;
        }

        return array_keys($out);
    }
}
