<?php

declare(strict_types=1);

namespace Kenzi\Chat;

/**
 * Settings helper for Kenzi Chat plugin options.
 */
final class Settings
{
    public const OPTION_NAME = 'kenzi_chat_settings';
    public const BASE_URL_OPTION = 'kenzi_base_url';
    public const CAPABILITIES_OPTION = 'kenzi_capabilities';
    public const DEFAULT_BASE_URL = 'https://app.kenzi.chat';

    /**
     * Get all settings with defaults.
     *
     * @return array{
     *     workspace_id: string,
     *     workspace_name: string,
     *     kenzi_secret: string,
     *     integration_id: string,
     *     widget_enabled: bool
     * }
     */
    public static function get(): array
    {
        $defaults = [
            'workspace_id' => '',
            'workspace_name' => '',
            'kenzi_secret' => '',
            'integration_id' => '',
            'widget_enabled' => false,
        ];

        /** @var array<string, mixed> $options */
        $options = get_option(self::OPTION_NAME, []);

        return array_merge($defaults, $options);
    }

    /**
     * Get the Kenzi base URL (for connect flow, postMessage validation, and widget).
     */
    public static function getBaseUrl(): string
    {
        $url = get_option(self::BASE_URL_OPTION, '');

        return is_string($url) && $url !== '' ? $url : self::DEFAULT_BASE_URL;
    }

    /**
     * Set the Kenzi base URL.
     */
    public static function setBaseUrl(string $url): void
    {
        $url = trim($url);

        if ($url === '' || $url === self::DEFAULT_BASE_URL) {
            delete_option(self::BASE_URL_OPTION);
        } else {
            update_option(self::BASE_URL_OPTION, $url);
        }
    }

    /**
     * Get the full connect URL for OAuth popup.
     */
    public static function getConnectUrl(): string
    {
        return rtrim(self::getBaseUrl(), '/') . '/connect/workspaces';
    }

    /**
     * Get the full upgrade URL for commerce capability popup.
     */
    public static function getUpgradeUrl(): string
    {
        return rtrim(self::getBaseUrl(), '/') . '/connect/upgrade';
    }

    /**
     * Check if a workspace is connected.
     */
    public static function isConnected(): bool
    {
        $options = self::get();

        return $options['workspace_id'] !== '';
    }

    /**
     * Check if the widget is enabled (and connected).
     */
    public static function isWidgetEnabled(): bool
    {
        $options = self::get();

        return self::isConnected() && ! empty($options['widget_enabled']);
    }

    /**
     * Get the widget loader URL, derived from base URL + workspace ID.
     *
     * Returns null if not connected.
     */
    public static function getWidgetUrl(): ?string
    {
        if (! self::isConnected()) {
            return null;
        }

        $options = self::get();

        return rtrim(self::getBaseUrl(), '/') . '/widget/loader.js?w=' . urlencode($options['workspace_id']);
    }

    /**
     * Get the workspace display name.
     */
    public static function getWorkspaceDisplay(): string
    {
        $options = self::get();

        return $options['workspace_name'] !== '' ? $options['workspace_name'] : $options['workspace_id'];
    }

    /**
     * Get the kenzi secret (for webhook verification and capability upgrade API).
     */
    public static function getKenziSecret(): string
    {
        $options = self::get();

        return $options['kenzi_secret'];
    }

    /**
     * Get the integration ID (numeric ID used for API calls to the Kenzi backend).
     */
    public static function getIntegrationId(): string
    {
        $options = self::get();

        return $options['integration_id'];
    }

    /**
     * Get the stored capabilities list.
     *
     * @return list<string>
     */
    public static function getCapabilities(): array
    {
        $raw = get_option(self::CAPABILITIES_OPTION, '');

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $raw)));
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
     * Detect platform capabilities based on installed plugins.
     *
     * @return list<string>
     */
    public static function detectCapabilities(): array
    {
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
        $options = self::get();

        $options['workspace_id'] = sanitize_text_field($data['workspace_id'] ?? '');
        $options['workspace_name'] = sanitize_text_field($data['workspace_name'] ?? '');
        $options['kenzi_secret'] = sanitize_text_field($data['secret'] ?? '');
        $options['integration_id'] = sanitize_text_field($data['integration_id'] ?? '');

        update_option(self::OPTION_NAME, $options);

        // Store detected capabilities at connection time
        self::setCapabilities(self::detectCapabilities());
    }

    /**
     * Save widget enabled state.
     */
    public static function setWidgetEnabled(bool $enabled): void
    {
        $options = self::get();

        // Can only enable if connected
        $options['widget_enabled'] = self::isConnected() && $enabled;

        update_option(self::OPTION_NAME, $options);
    }

    /**
     * Clear connection data (disconnect workspace).
     *
     * Preserves developer settings like base URL.
     */
    public static function disconnect(): void
    {
        delete_option(self::OPTION_NAME);
        delete_option(self::CAPABILITIES_OPTION);
    }

    /**
     * Reset all settings including developer options.
     */
    public static function reset(): void
    {
        delete_option(self::OPTION_NAME);
        delete_option(self::BASE_URL_OPTION);
        delete_option(self::CAPABILITIES_OPTION);
    }
}
