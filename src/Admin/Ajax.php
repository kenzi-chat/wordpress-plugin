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
    }

    /**
     * Save connection credentials received from the Kenzi Connect popup.
     */
    public static function saveConnection(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'kenzi-chat'));
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'kenzi_save_connection')) {
            wp_send_json_error(__('Invalid nonce', 'kenzi-chat'));
            return;
        }
        $capabilitiesRaw = sanitize_text_field(wp_unslash($_POST['capabilities'] ?? ''));
        $allowed = ['commerce'];
        $capabilities = $capabilitiesRaw !== ''
            ? array_values(array_intersect(array_map('trim', explode(',', $capabilitiesRaw)), $allowed))
            : [];
        Settings::saveConnection([
            'workspace_id' => sanitize_text_field(wp_unslash($_POST['workspace_id'] ?? '')),
            'secret' => sanitize_text_field(wp_unslash($_POST['secret'] ?? '')),
            'integration_id' => sanitize_text_field(wp_unslash($_POST['integration_id'] ?? '')),
            'capabilities' => $capabilities,
        ]);

        // Workspace name is only used in the success notice, not persisted.
        $workspaceName = sanitize_text_field(wp_unslash($_POST['workspace_name'] ?? ''));
        set_transient('kenzi_chat_notice', 'connected', 30);
        if ($workspaceName !== '') {
            set_transient('kenzi_chat_connected_name', $workspaceName, 30);
        }

        wp_send_json_success();
    }

    /**
     * Notify the Kenzi backend that this integration is disconnecting.
     *
     * Returns true if the backend was notified (or no notification was needed),
     * false if the API call failed.
     */
    private static function notifyBackendDisconnect(): bool
    {
        $integrationId = Settings::getIntegration();
        $kenziSecret = Settings::getSharedSecret();
        $baseUrl = Settings::getAppBase();

        if ($integrationId === null) {
            return true;
        }

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

        // 200 = disconnected, 404 = already gone — both are fine.
        return ! is_wp_error($response) && ($code === 200 || $code === 404);
    }

    /**
     * Disconnect the integration from Kenzi and clear local settings.
     */
    public static function disconnect(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'kenzi-chat'));
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'kenzi_disconnect')) {
            wp_send_json_error(__('Invalid nonce', 'kenzi-chat'));
            return;
        }

        $backendNotified = self::notifyBackendDisconnect();

        Settings::disconnect();

        if (! $backendNotified) {
            wp_send_json_success(['warning' => __('Disconnected locally, but failed to notify Kenzi', 'kenzi-chat')]);
            return;
        }

        wp_send_json_success();
    }
}
