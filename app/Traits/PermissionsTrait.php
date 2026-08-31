<?php

namespace App\Traits;

use ReflectionClass;

/**
 * LMS removal phase 2 (2026-08-31) — dropped ~25 permission groups that
 * gated deleted LMS/coach admin surfaces: blog + blog category + blog
 * comment, basic payment, contact message, landing page message, coach
 * landing pages, customer, menu builder, page builder, newsletter,
 * testimonial, faq, instructor request, course, certificate, badges, order,
 * coupon, withdraw, site appearance/section, brand, footer, social link.
 * getSuperAdminPermissions() reflects over whatever static properties remain
 * on this trait to seed the `permissions` table (RolePermissionSeeder), so
 * removing the property removes the seeded permission — no separate cleanup
 * needed there.
 */
trait PermissionsTrait
{
    public static array $dashboardPermissions = [
        'group_name' => 'dashboard',
        'permissions' => [
            'dashboard.view',
        ],
    ];

    public static array $adminProfilePermissions = [
        'group_name' => 'admin profile',
        'permissions' => [
            'admin.profile.view',
            'admin.profile.update',
        ],
    ];

    public static array $adminPermissions = [
        'group_name' => 'admin',
        'permissions' => [
            'admin.view',
            'admin.create',
            'admin.store',
            'admin.edit',
            'admin.update',
            'admin.delete',
        ],
    ];

    public static array $rolePermissions = [
        'group_name' => 'role',
        'permissions' => [
            'role.view',
            'role.create',
            'role.store',
            'role.assign',
            'role.edit',
            'role.update',
            'role.delete',
        ],
    ];

    public static array $settingPermissions = [
        'group_name' => 'setting',
        'permissions' => [
            'setting.view',
            'setting.update',
        ],
    ];

    public static array $currencyPermissions = [
        'group_name' => 'currency',
        'permissions' => [
            'currency.view',
            'currency.create',
            'currency.store',
            'currency.edit',
            'currency.update',
            'currency.delete',
        ],
    ];

    public static array $languagePermissions = [
        'group_name' => 'language',
        'permissions' => [
            'language.view',
            'language.create',
            'language.store',
            'language.edit',
            'language.update',
            'language.delete',
            'language.translate',
            'language.single.translate',
        ],
    ];

    public static array $locationPermissions = [
        'group_name' => 'locations',
        'permissions' => [
            'location.view',
            'location.create',
            'location.store',
            'location.edit',
            'location.update',
            'location.delete',
        ],
    ];

    public static array $addonsPermissions = [
        'group_name' => 'Addons',
        'permissions' => [
            'addon.view',
            'addon.install',
            'addon.update',
            'addon.status.change',
            'addon.remove',
        ],
    ];

    private static function getSuperAdminPermissions(): array
    {
        $reflection = new ReflectionClass(__TRAIT__);
        $properties = $reflection->getStaticProperties();

        $permissions = [];
        foreach ($properties as $value) {
            if (is_array($value)) {
                $permissions[] = [
                    'group_name' => $value['group_name'],
                    'permissions' => (array) $value['permissions'],
                ];
            }
        }

        return $permissions;
    }
}
