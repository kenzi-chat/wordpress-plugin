<?php

declare(strict_types=1);

namespace Kenzi\Chat;

defined('ABSPATH') || exit;

/**
 * Settings helper for Kenzi Chat plugin options.
 *
 * Each setting is stored as an individual wp_option row, following core
 * WordPress convention (e.g. blogname, siteurl, admin_email).
 */
final class Settings
{
    public const OPTION_WIDGET_ID = 'kenzi_widget_id';
    public const OPTION_SECRET = 'kenzi_shared_secret';
    public const OPTION_GRANTS = 'kenzi_grants';
    public const OPTION_WIDGET_ENABLED = 'kenzi_widget_enabled';
    public const DEFAULT_APP_BASE = 'https://app.kenzi.chat';
    public const DEFAULT_STATIC_BASE = 'https://static.kenzi.chat';

    /**
     * Get the Kenzi app base URL (connect flow, API calls, webhooks).
     *
     * Reads from the KENZI_APP_BASE environment variable, falling back
     * to production. Set the env var for local development.
     */
    public static function getAppBase(): string
    {
        $env = getenv('KENZI_APP_BASE');

        return is_string($env) && $env !== '' ? $env : self::DEFAULT_APP_BASE;
    }

    /**
     * Get the Kenzi static base URL (widget assets).
     *
     * Reads from the KENZI_STATIC_BASE environment variable, falling back
     * to production.
     */
    public static function getStaticBase(): string
    {
        $env = getenv('KENZI_STATIC_BASE');

        return is_string($env) && $env !== '' ? $env : self::DEFAULT_STATIC_BASE;
    }

    /**
     * Get the Kenzi Connect popup URL.
     */
    public static function getConnectUrl(): string
    {
        return rtrim(self::getAppBase(), '/') . '/connect';
    }

    /**
     * Check if a workspace is connected (shared secret exists).
     */
    public static function isConnected(): bool
    {
        return (bool) get_option(self::OPTION_SECRET);
    }

    /**
     * Check if the widget is enabled (and connected).
     */
    public static function isWidgetEnabled(): bool
    {
        return self::isConnected() && (bool) get_option(self::OPTION_WIDGET_ENABLED);
    }

    /**
     * Get the widget loader URL for the connected workspace.
     */
    public static function getWidgetUrl(): ?string
    {
        $workspaceId = get_option(self::OPTION_WIDGET_ID);

        if (! $workspaceId) {
            return null;
        }

        return rtrim(self::getStaticBase(), '/') . '/widget/loader.js?w=' . urlencode($workspaceId);
    }

    /**
     * Get the shared secret for webhook HMAC verification and API auth.
     */
    public static function getSharedSecret(): ?string
    {
        return get_option(self::OPTION_SECRET) ?: null;
    }

    /**
     * Get the stored grants list.
     *
     * @return list<string>
     */
    public static function getGrants(): array
    {
        $raw = get_option(self::OPTION_GRANTS);

        return is_array($raw) ? $raw : [];
    }

    /**
     * Check if a specific grant is active.
     */
    public static function hasGrant(string $grant): bool
    {
        return in_array($grant, self::getGrants(), true);
    }

    /**
     * Derive the instance key from the site's home URL.
     *
     * Uses PHP's URL parser to extract host (lowercased) and path
     * (case-preserved, trailing slash stripped). Port, query, and
     * fragment are dropped. See §4 of the plugin authoring guide.
     */
    public static function getInstanceKey(): string
    {
        $url = home_url();

        return strtolower(wp_parse_url($url, PHP_URL_HOST) ?? '')
             . rtrim(wp_parse_url($url, PHP_URL_PATH) ?? '', '/');
    }

    /**
     * Detect supported grants based on installed plugins.
     *
     * @return list<string>
     */
    public static function detectGrants(): array
    {
        // is_plugin_active() is only available in admin context.
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $grants = [];

        if (is_plugin_active('kenzi-commerce/kenzi-commerce.php')) {
            $grants[] = 'commerce';
        }

        return $grants;
    }

    /**
     * Save widget enabled state.
     */
    public static function setWidgetEnabled(bool $enabled): void
    {
        update_option(self::OPTION_WIDGET_ENABLED, self::isConnected() && $enabled ? '1' : '', false);
    }

}
