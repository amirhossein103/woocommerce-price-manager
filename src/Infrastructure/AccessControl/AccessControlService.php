<?php declare(strict_types=1);

namespace WPM\Infrastructure\AccessControl;

defined('ABSPATH') || exit;

/**
 * Manages user roles, capabilities, and pricing authorization checks.
 */
final class AccessControlService
{
    public const CAPABILITY = 'manage_product_pricing';
    public const ROLE_SLUG = 'pricing_manager';
    public const ROLE_NAME = 'Pricing Manager';

    /**
     * Install custom roles and grant capabilities to Administrator and Shop Manager.
     */
    public function install(): void
    {
        if (function_exists('add_role')) {
            add_role(
                self::ROLE_SLUG,
                self::ROLE_NAME,
                [
                    'read' => true,
                    self::CAPABILITY => true,
                ]
            );
        }

        if (function_exists('get_role')) {
            $admin = get_role('administrator');
            if ($admin !== null) {
                $admin->add_cap(self::CAPABILITY);
            }

            $shopManager = get_role('shop_manager');
            if ($shopManager !== null) {
                $shopManager->add_cap(self::CAPABILITY);
            }
        }
    }

    /**
     * Remove capabilities and custom role upon plugin uninstall/deactivation.
     */
    public function uninstall(): void
    {
        if (function_exists('remove_role')) {
            remove_role(self::ROLE_SLUG);
        }

        if (function_exists('get_role')) {
            $admin = get_role('administrator');
            if ($admin !== null) {
                $admin->remove_cap(self::CAPABILITY);
            }

            $shopManager = get_role('shop_manager');
            if ($shopManager !== null) {
                $shopManager->remove_cap(self::CAPABILITY);
            }
        }
    }

    /**
     * Check if a given user (or current user if null) has pricing management permission.
     *
     * @param int|null $userId User ID to check, or null for current user.
     * @return bool True if authorized, false otherwise.
     */
    public function canManagePricing(?int $userId = null): bool
    {
        if ($userId === null || $userId <= 0) {
            if (function_exists('current_user_can')) {
                return current_user_can(self::CAPABILITY);
            }
            return false;
        }

        if (function_exists('user_can')) {
            return user_can($userId, self::CAPABILITY);
        }

        return false;
    }
}
