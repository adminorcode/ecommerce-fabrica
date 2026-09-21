<?php

declare(strict_types=1);

namespace Petshop\Core\Personalization\Infrastructure;

defined('ABSPATH') || exit;

final class Capabilities
{
    public const MANAGE = 'manage_petshop_personalizations';

    /**
     * @var list<string>
     */
    private const ROLES = ['administrator', 'shop_manager'];

    public static function ensureAssigned(): void
    {
        foreach (self::ROLES as $roleName) {
            $role = get_role($roleName);
            if ($role instanceof \WP_Role && !$role->has_cap(self::MANAGE)) {
                $role->add_cap(self::MANAGE);
            }
        }
    }

    public static function currentUserCanManage(): bool
    {
        return current_user_can(self::MANAGE);
    }
}
