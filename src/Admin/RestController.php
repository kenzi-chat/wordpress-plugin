<?php

declare(strict_types=1);

namespace Kenzi\Chat\Admin;

use Kenzi\Chat\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * REST API controller for Kenzi Chat plugin endpoints.
 *
 * Registers four routes under the `kenzi` namespace:
 * - POST /kenzi/connect      — §6: write shared_secret + grants to local config
 * - GET  /kenzi/integration  — §8: proxy GET /api/integration to Kenzi
 * - POST /kenzi/configure    — §7: mint credentials + PATCH config to Kenzi
 * - POST /kenzi/disconnect   — §10: release claim + clear local config
 */
final class RestController
{
    /**
     * Register all REST routes.
     */
    public static function register(): void
    {
        register_rest_route('kenzi', '/connect', [
            'methods' => 'POST',
            'callback' => [self::class, 'connect'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
        ]);

        register_rest_route('kenzi', '/integration', [
            'methods' => 'GET',
            'callback' => [self::class, 'integration'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
        ]);

        register_rest_route('kenzi', '/configure', [
            'methods' => 'POST',
            'callback' => [self::class, 'configure'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
        ]);

        register_rest_route('kenzi', '/disconnect', [
            'methods' => 'POST',
            'callback' => [self::class, 'disconnect'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
        ]);
    }

    /**
     * Permission callback — admin-only.
     */
    public static function checkAdminPermission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * POST /kenzi/connect — §6
     *
     * Writes shared_secret and grants to local plugin config.
     * Cheap, atomic, no Kenzi API calls.
     */
    public static function connect(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $sharedSecret = $request->get_param('shared_secret');
        $workspaceId = $request->get_param('workspace_id');
        $grants = $request->get_param('grants');

        if (
            ! is_string($sharedSecret) ||
            ! str_starts_with($sharedSecret, 'ss_') ||
            ! is_string($workspaceId) ||
            $workspaceId === '' ||
            ! is_array($grants)
        ) {
            return new WP_Error('invalid_payload', 'Bad payload', ['status' => 422]);
        }

        update_option(Settings::OPTION_SECRET, $sharedSecret, false);
        update_option(Settings::OPTION_WIDGET_ID, sanitize_text_field($workspaceId), false);
        update_option(Settings::OPTION_GRANTS, $grants, false);

        return new WP_REST_Response(['ok' => true], 200);
    }

    /**
     * GET /kenzi/integration — §8
     *
     * Pure proxy to GET /api/integration on the Kenzi backend.
     * No caching, no transformation.
     */
    public static function integration(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $secret = get_option(Settings::OPTION_SECRET);

        if (! $secret) {
            return new WP_Error('not_connected', 'Not connected', ['status' => 404]);
        }

        $url = rtrim(Settings::getAppBase(), '/') . '/api/integration';

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $secret,
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('upstream_unreachable', 'Network error', ['status' => 504]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($body)) {
            return new WP_Error('upstream_bad_response', 'Unexpected upstream response', ['status' => 502]);
        }

        $rest = new WP_REST_Response($body, $code);
        $rest->header('Cache-Control', 'no-store');

        return $rest;
    }

    /**
     * POST /kenzi/configure — §7
     *
     * Builds the per-platform config payload, minting platform-side
     * credentials as needed, then PATCHes {config: {...}} to Kenzi.
     *
     * The base config (api_url, admin_url) is always included. The
     * `kenzi_configure_config` filter lets add-on plugins (WooCommerce)
     * extend it with credentials and webhook IDs.
     */
    public static function configure(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $secret = get_option(Settings::OPTION_SECRET);

        if (! $secret) {
            return new WP_Error('not_connected', 'Not connected', ['status' => 409]);
        }

        // Base config — always present regardless of grants.
        $config = [
            'rest_url' => rest_url(),
            'admin_url' => admin_url(),
        ];

        /**
         * Filters the config payload before it is PATCHed to Kenzi.
         *
         * Add-on plugins (e.g. Kenzi Commerce) hook into this to mint
         * platform-side credentials (WC REST API keys, webhooks) and
         * merge them into the config object.
         *
         * Return a WP_Error to abort the configure step.
         *
         * @param array<string, mixed> $config The config payload.
         * @return array<string, mixed>|WP_Error
         */
        /** @var array<string, mixed>|WP_Error $config */
        $config = apply_filters('kenzi_configure_config', $config);

        if (is_wp_error($config)) {
            return $config;
        }

        // PATCH {config: {...}} to Kenzi.
        $url = rtrim(Settings::getAppBase(), '/') . '/api/integration';

        $response = wp_remote_request($url, [
            'method' => 'PATCH',
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $secret,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(['config' => $config]),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('upstream_unreachable', 'Network error', ['status' => 502]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($body)) {
            return new WP_Error('upstream_bad_response', 'Unexpected upstream response', ['status' => 502]);
        }

        // Map upstream status codes: pass through 200 and 422 (validation),
        // map all other failures to 502 (gateway error) so WP REST consumers
        // don't confuse upstream issues with local auth failures (§7.6).
        $localCode = match (true) {
            $code === 200 => 200,
            $code === 422 => 422,
            default => 502,
        };

        return new WP_REST_Response($body, $localCode);
    }

    /**
     * POST /kenzi/disconnect — §10
     *
     * Best-effort PATCH /api/integration {claim: null} to Kenzi,
     * then unconditional clear of local config.
     */
    public static function disconnect(WP_REST_Request $request): WP_REST_Response
    {
        $secret = get_option(Settings::OPTION_SECRET);

        if ($secret) {
            // Best-effort — ignore any failure.
            wp_remote_request(rtrim(Settings::getAppBase(), '/') . '/api/integration', [
                'method' => 'PATCH',
                'timeout' => 5,
                'headers' => [
                    'Authorization' => 'Bearer ' . $secret,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode(['claim' => null]),
            ]);
        }

        delete_option(Settings::OPTION_SECRET);
        delete_option(Settings::OPTION_GRANTS);
        delete_option(Settings::OPTION_WIDGET_ID);
        delete_option(Settings::OPTION_WIDGET_ENABLED);

        /**
         * Fires after Kenzi Chat has cleared its connection options.
         *
         * Add-on plugins (e.g. Kenzi Commerce) hook into this to clean up
         * their own resources — webhooks, API keys, etc.
         */
        do_action('kenzi_chat_disconnected');

        return new WP_REST_Response(['ok' => true], 200);
    }
}
