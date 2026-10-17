<?php

namespace Hcode\Security;

// Compatibility facade: the active catalogue lives in Model\Permissions.
class Permissions
{
    public static function definitions(): array
    {
        return array_map(static function ($definition) {
            return $definition['description'];
        }, \Hcode\Model\Permissions::definitions());
    }

    public static function routeMap(): array
    {
        return \Hcode\Model\Permissions::routeMap();
    }

    public static function defaultProfilePermissions(): array
    {
        return \Hcode\Model\Permissions::defaultProfilePermissions();
    }
}
