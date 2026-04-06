<?php

declare(strict_types=1);

namespace Kenzi\Chat;

/**
 * Settings helper for Kenzi Chat plugin options.
 *
 * Each setting is stored as an individual wp_option row, following core
 * WordPress convention (e.g. blogname, siteurl, admin_email).
 */
final class Settings
{
    public const OPTION_WORKSPACE_ID = 'kenzi_workspace_id';
    public const OPTION_SECRET = 'kenzi_shared_secret';
    public const OPTION_INTEGRATION_ID = 'kenzi_integration_id';
    public const OPTION_WIDGET_ENABLED = 'kenzi_widget_enabled';
    public const CAPABILITIES_OPTION = 'kenzi_capabilities';
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
     * Check if a workspace is connected.
     */
    public static function isConnected(): bool
    {
        return (bool) get_option(self::OPTION_WORKSPACE_ID);
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
        $workspaceId = get_option(self::OPTION_WORKSPACE_ID);

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
     * Get the integration ID for API calls to the Kenzi backend.
     */
    public static function getIntegration(): ?string
    {
        return get_option(self::OPTION_INTEGRATION_ID) ?: null;
    }

    /**
     * Get the stored capabilities list.
     *
     * @return list<string>
     */
    public static function getCapabilities(): array
    {
        $raw = get_option(self::CAPABILITIES_OPTION);

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Check if a specific capability is enabled.
     */
    public static function hasCapability(string $capability): bool
    {
        return in_array($capability, self::getCapabilities(), true);
    }

    /**
     * Save capabilities list.
     *
     * @param list<string> $capabilities
     */
    public static function setCapabilities(array $capabilities): void
    {
        if ($capabilities === []) {
            delete_option(self::CAPABILITIES_OPTION);
        } else {
            update_option(self::CAPABILITIES_OPTION, implode(',', $capabilities));
        }
    }

    /**
     * Derive the instance key from the site's home URL.
     *
     * Strips scheme and trailing slashes to produce a stable identity
     * (e.g., "mystore.com" or "example.com/store2").
     */
    public static function getInstanceKey(): string
    {
        return preg_replace('#^https?://#', '', rtrim(home_url(), '/'));
    }

    /**
     * Detect platform capabilities based on installed plugins.
     *
     * @return list<string>
     */
    public static function detectCapabilities(): array
    {
        // is_plugin_active() is only available in admin context.
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $capabilities = [];

        if (is_plugin_active('kenzi-commerce/kenzi-commerce.php')) {
            $capabilities[] = 'commerce';
        }

        return $capabilities;
    }

    /**
     * Save connection data from the Kenzi Connect flow.
     *
     * @param array<string, string> $data
     */
    public static function saveConnection(array $data): void
    {
        update_option(self::OPTION_WORKSPACE_ID, $data['workspace_id'], false);
        update_option(self::OPTION_SECRET, $data['secret'], false);
        update_option(self::OPTION_INTEGRATION_ID, $data['integration_id'], false);

        // Use the capabilities confirmed by the user in the Connect popup, not auto-detected from
        // installed plugins. This respects the user's choice to disable a capability even when
        // its plugin is active.
        self::setCapabilities($data['capabilities'] ?? []);
    }

    /**
     * Save widget enabled state.
     */
    public static function setWidgetEnabled(bool $enabled): void
    {
        update_option(self::OPTION_WIDGET_ENABLED, self::isConnected() && $enabled ? '1' : '', false);
    }

    /**
     * Clear connection data (disconnect workspace).
     */
    public static function disconnect(): void
    {
        delete_option(self::OPTION_WORKSPACE_ID);
        delete_option(self::OPTION_SECRET);
        delete_option(self::OPTION_INTEGRATION_ID);
        delete_option(self::OPTION_WIDGET_ENABLED);
        delete_option(self::CAPABILITIES_OPTION);

        /**
         * Fires after Kenzi Chat has cleared its connection options.
         *
         * Add-on plugins (e.g. Kenzi Commerce) hook into this to clean up
         * their own resources — webhooks, API keys, etc.
         */
        do_action('kenzi_chat_disconnected');
    }
}
