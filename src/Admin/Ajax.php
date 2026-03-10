<?php

declare(strict_types=1);

namespace Kenzi\Chat\Admin;

use Kenzi\Chat\Settings;

/**
 * AJAX handlers for Kenzi Chat admin actions.
 */
final class Ajax
{
    /**
     * Register AJAX action hooks.
     */
    public static function register(): void
    {
        add_action('wp_ajax_kenzi_save_connection', [self::class, 'saveConnection']);
        add_action('wp_ajax_kenzi_disconnect', [self::class, 'disconnect']);
        add_action('wp_ajax_kenzi_reset_settings', [self::class, 'resetSettings']);
    }

    /**
     * Handle connection save from Kenzi Connect popup flow.
     */
    public static function saveConnection(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (! wp_verify_nonce($_POST['_wpnonce'] ?? '', 'kenzi_save_connection')) {
            wp_send_json_error('Invalid nonce');
        }

        Settings::saveConnection([
            'workspace_id' => $_POST['workspace_id'] ?? '',
            'workspace_name' => $_POST['workspace_name'] ?? '',
            'secret' => $_POST['secret'] ?? '',
            'integration_id' => $_POST['integration_id'] ?? '',
        ]);

        wp_send_json_success();
    }

    /**
     * Disconnect the integration from Kenzi and clear local settings.
     */
    public static function disconnect(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (! wp_verify_nonce($_POST['_wpnonce'] ?? '', 'kenzi_disconnect')) {
            wp_send_json_error('Invalid nonce');
        }

        $integrationId = Settings::getIntegrationId();
        $kenziSecret = Settings::getKenziSecret();
        $baseUrl = Settings::getBaseUrl();

        if ($integrationId !== '') {
            $url = rtrim($baseUrl, '/') . '/api/accounts/integrations/' . urlencode($integrationId) . '/disconnect';

            $response = wp_remote_request($url, [
                'method' => 'PATCH',
                'timeout' => 15,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $kenziSecret,
                ],
                'body' => '',
            ]);

            $code = wp_remote_retrieve_response_code($response);

            // 200 = disconnected, 404 = already gone — both are fine
            if (is_wp_error($response) || ($code !== 200 && $code !== 404)) {
                wp_send_json_error('Failed to disconnect from Kenzi');
            }
        }

        Settings::disconnect();

        wp_send_json_success();
    }

    /**
     * Handle settings reset (disconnect workspace).
     */
    public static function resetSettings(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (! wp_verify_nonce($_POST['_wpnonce'] ?? '', 'kenzi_reset_settings')) {
            wp_send_json_error('Invalid nonce');
        }

        Settings::reset();

        wp_send_json_success();
    }
}
