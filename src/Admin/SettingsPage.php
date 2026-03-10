<?php

declare(strict_types=1);

namespace Kenzi\Chat\Admin;

use Kenzi\Chat\Settings;

/**
 * Admin settings page for Kenzi Chat.
 *
 * Registers a top-level admin menu page for Kenzi Chat using
 * WordPress's native admin menu API.
 */
class SettingsPage
{
    private const PAGE_SLUG = 'kenzi-chat';

    /**
     * Register the admin menu page and the save handler.
     *
     * Called on the `admin_menu` hook.
     */
    public function register(): void
    {
        add_menu_page(
            __('Kenzi Chat', 'kenzi-chat'),
            __('Kenzi Chat', 'kenzi-chat'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render'],
            'dashicons-format-chat',
            58
        );

        add_action('admin_init', [$this, 'handleSave']);
    }

    /**
     * Handle form POST saves (widget enabled toggle and base URL update).
     *
     * Called on `admin_init` for every admin page load; exits early if not
     * a save for this plugin.
     */
    public function handleSave(): void
    {
        if (! isset($_POST['kenzi_save_widget']) && ! isset($_POST['kenzi_save_base_url'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'kenzi-chat'), 403);
        }

        if (isset($_POST['kenzi_save_widget']) && $_POST['kenzi_save_widget'] === '1') {
            if (! wp_verify_nonce($_POST['_kenzi_widget_nonce'] ?? '', 'kenzi_save_widget_settings')) {
                wp_die(esc_html__('Security check failed.', 'kenzi-chat'), 403);
            }

            Settings::setWidgetEnabled(isset($_POST['widget_enabled']));

            wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&updated=1'));
            exit;
        }

        if (isset($_POST['kenzi_save_base_url']) && $_POST['kenzi_save_base_url'] === '1') {
            if (! wp_verify_nonce($_POST['_kenzi_widget_nonce'] ?? '', 'kenzi_save_widget_settings')) {
                wp_die(esc_html__('Security check failed.', 'kenzi-chat'), 403);
            }

            $baseUrl = isset($_POST['base_url']) ? esc_url_raw(trim($_POST['base_url'])) : '';
            Settings::setBaseUrl($baseUrl);

            wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&updated=1'));
            exit;
        }
    }

    /**
     * Render the settings page HTML.
     */
    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $isConnected = Settings::isConnected();
        $widgetEnabled = Settings::isWidgetEnabled();
        $workspaceDisplay = Settings::getWorkspaceDisplay();
        $baseUrl = Settings::getBaseUrl();
        $connectUrl = Settings::getConnectUrl();
        $storeUrl = home_url();
        $adminAjaxUrl = admin_url('admin-ajax.php');
        $settingsUrl = admin_url('admin.php?page=' . self::PAGE_SLUG);
        $saveNonce = wp_create_nonce('kenzi_save_connection');
        $disconnectNonce = wp_create_nonce('kenzi_disconnect');
        $resetNonce = wp_create_nonce('kenzi_reset_settings');
        $capabilities = Settings::detectCapabilities();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Kenzi Chat', 'kenzi-chat'); ?></h1>

            <?php if (isset($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved.', 'kenzi-chat'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['connected'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Successfully connected to Kenzi!', 'kenzi-chat'); ?></p>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e('Connection', 'kenzi-chat'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Workspace', 'kenzi-chat'); ?></th>
                    <td>
                        <?php if ($isConnected): ?>
                            <p style="margin: 0 0 10px; color: #2e7d32;">
                                <span class="dashicons dashicons-yes-alt" style="color: #2e7d32;"></span>
                                <?php
                                printf(
                                    /* translators: %s: workspace name */
                                    esc_html__('Connected to: %s', 'kenzi-chat'),
                                    '<strong>' . esc_html($workspaceDisplay) . '</strong>'
                                );
                                ?>
                            </p>
                            <button type="button" class="button" onclick="kenziConnect()">
                                <?php esc_html_e('Change Workspace', 'kenzi-chat'); ?>
                            </button>
                            <button type="button" class="button" style="color: #d63638; border-color: #d63638;" onclick="kenziDisconnect()">
                                <?php esc_html_e('Disconnect', 'kenzi-chat'); ?>
                            </button>
                        <?php else: ?>
                            <p style="margin: 0 0 10px;">
                                <?php esc_html_e('Connect your site to a Kenzi workspace to enable chat.', 'kenzi-chat'); ?>
                            </p>
                            <button type="button" onclick="kenziConnect()" style="display: inline-flex; align-items: center; gap: 10px; padding: 10px 20px; background-color: #358d8d; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background-color 0.2s, box-shadow 0.2s; line-height: 1;"
                                onmouseover="this.style.backgroundColor='#2d7a7a'; this.style.boxShadow='0 4px 12px rgba(53,141,141,0.25)';"
                                onmouseout="this.style.backgroundColor='#358d8d'; this.style.boxShadow='none';">
                                <svg width="22" height="22" viewBox="0 0 240 240" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="120" cy="120" r="110" fill="#6cb7b7"/>
                                    <path d="M155.8,72.56c-9.96-11.74-25.65-18.33-42.32-18.33-31.53,0-57.5,25.97-57.5,57.5s25.97,57.5,57.5,57.5c11.23,0,22.04-3.34,31.3-9.56,2.17-1.44,2.71-4.45,1.26-6.62-1.44-2.17-4.45-2.71-6.62-1.26-7.56,5.06-16.39,7.78-25.54,7.78-25.18,0-45.64-20.45-45.64-45.64s20.45-45.64,45.64-45.64c13.47,0,26.3,5.57,34.26,14.95,6.45,7.63,9.17,17.19,7.61,26.98-.12.92-3.17,22.41-28.1,22.41-4.1,0-7.35-1.48-9.96-4.52-3.44-3.94-4.95-9.55-5.17-14.57,21.42-2.68,30.03-16.53,30.43-17.19,1.48-2.48.72-5.76-1.76-7.24-2.48-1.48-5.73-.73-7.2,1.76-.3.48-6.85,10.53-23.64,12.29l8.5-18.4c1.22-2.66.06-5.79-2.6-7.01-2.66-1.22-5.8-.06-7.02,2.6l-24.35,52.84c-1.22,2.66-.06,5.79,2.6,7.01.72.33,1.48.49,2.22.49,2,.0,3.92-1.15,4.82-3.08l8.14-17.68c1.02,5.27,3.18,10.7,7.04,15.18,4.64,5.38,10.87,8.22,18.02,8.22,27.25,0,37.18-20.62,38.63-31.6,2.05-12.83-1.49-25.37-10-35.39Z" fill="#ece8e0"/>
                                </svg>
                                <span><?php esc_html_e('Connect to Kenzi', 'kenzi-chat'); ?></span>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Widget Settings', 'kenzi-chat'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('kenzi_save_widget_settings', '_kenzi_widget_nonce'); ?>
                <input type="hidden" name="kenzi_save_widget" value="1">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Widget', 'kenzi-chat'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="widget_enabled"
                                       value="1"
                                       <?php checked($widgetEnabled); ?>
                                       <?php disabled(! $isConnected); ?>>
                                <?php esc_html_e('Show Kenzi chat widget on all pages', 'kenzi-chat'); ?>
                            </label>
                            <p class="description">
                                <?php if (! $isConnected): ?>
                                    <span style="color: #d63638;">
                                        <?php esc_html_e('Connect to Kenzi first to enable the widget.', 'kenzi-chat'); ?>
                                    </span>
                                <?php else: ?>
                                    <?php esc_html_e('When enabled, the chat widget appears on all pages.', 'kenzi-chat'); ?>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Save Changes', 'kenzi-chat'); ?>
                    </button>
                </p>
            </form>

            <h2><?php esc_html_e('Developer Tools', 'kenzi-chat'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Base URL', 'kenzi-chat'); ?></th>
                    <td>
                        <form method="post" action="" style="margin: 0;">
                            <?php wp_nonce_field('kenzi_save_widget_settings', '_kenzi_widget_nonce'); ?>
                            <input type="hidden" name="kenzi_save_base_url" value="1">
                            <input type="text"
                                   name="base_url"
                                   value="<?php echo esc_attr($baseUrl); ?>"
                                   class="regular-text"
                                   placeholder="<?php echo esc_attr(Settings::DEFAULT_BASE_URL); ?>">
                            <button type="submit" class="button"><?php esc_html_e('Update', 'kenzi-chat'); ?></button>
                            <p class="description">
                                <?php esc_html_e('Change for local development or staging. Default:', 'kenzi-chat'); ?>
                                <code><?php echo esc_html(Settings::DEFAULT_BASE_URL); ?></code>
                            </p>
                        </form>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Reset', 'kenzi-chat'); ?></th>
                    <td>
                        <button type="button" class="button" style="color: #d63638; border-color: #d63638;" onclick="kenziReset()">
                            <?php esc_html_e('Reset All Settings', 'kenzi-chat'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Disconnect workspace and reset all settings to defaults.', 'kenzi-chat'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <script>
            (function() {
                let currentNonce = null;

                window.kenziConnect = function() {
                    const bytes = new Uint8Array(32);
                    crypto.getRandomValues(bytes);
                    currentNonce = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');

                    const storeUrl = <?php echo wp_json_encode($storeUrl); ?>;
                    const capabilities = <?php echo wp_json_encode($capabilities); ?>;
                    const params = {
                        platform: 'wordpress',
                        store_key: storeUrl,
                        origin: storeUrl,
                        nonce: currentNonce
                    };

                    if (capabilities.length > 0) {
                        params.capabilities = capabilities.join(',');
                    }

                    const connectUrl = <?php echo wp_json_encode($connectUrl); ?> + '?' + new URLSearchParams(params);

                    const width = 500, height = 600;
                    const left = Math.round((screen.width - width) / 2);
                    const top = Math.round((screen.height - height) / 2);
                    const popup = window.open(
                        connectUrl,
                        'kenzi_connect',
                        `popup,width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
                    );

                    if (!popup || popup.closed) {
                        alert(<?php echo wp_json_encode(__('Popup was blocked. Please allow popups for this site.', 'kenzi-chat')); ?>);
                        currentNonce = null;
                    }
                };

                window.kenziReset = function() {
                    if (!confirm(<?php echo wp_json_encode(__('Reset all Kenzi Chat settings? This will disconnect your workspace.', 'kenzi-chat')); ?>)) {
                        return;
                    }

                    fetch(<?php echo wp_json_encode($adminAjaxUrl); ?>, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'kenzi_reset_settings',
                            _wpnonce: <?php echo wp_json_encode($resetNonce); ?>
                        })
                    })
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            window.location.href = <?php echo wp_json_encode($settingsUrl); ?>;
                        } else {
                            alert(<?php echo wp_json_encode(__('Reset failed:', 'kenzi-chat')); ?> + ' ' + (result.data || 'Unknown error'));
                        }
                    })
                    .catch(() => alert(<?php echo wp_json_encode(__('Reset failed. Please try again.', 'kenzi-chat')); ?>));
                };

                window.kenziDisconnect = function() {
                    if (!confirm(<?php echo wp_json_encode(__('Disconnect from Kenzi? This will remove the workspace connection.', 'kenzi-chat')); ?>)) {
                        return;
                    }

                    fetch(<?php echo wp_json_encode($adminAjaxUrl); ?>, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'kenzi_disconnect',
                            _wpnonce: <?php echo wp_json_encode($disconnectNonce); ?>
                        })
                    })
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            window.location.href = <?php echo wp_json_encode($settingsUrl); ?>;
                        } else {
                            alert(<?php echo wp_json_encode(__('Disconnect failed:', 'kenzi-chat')); ?> + ' ' + (result.data || 'Unknown error'));
                        }
                    })
                    .catch(() => alert(<?php echo wp_json_encode(__('Disconnect failed. Please try again.', 'kenzi-chat')); ?>));
                };

                window.addEventListener('message', function(event) {
                    const expectedOrigin = new URL(<?php echo wp_json_encode($connectUrl); ?>).origin;
                    if (event.origin !== expectedOrigin) {
                        return;
                    }

                    if (event.data?.type === 'kenzi_connected') {
                        if (event.data.nonce !== currentNonce) {
                            alert(<?php echo wp_json_encode(__('Security validation failed. Please try again.', 'kenzi-chat')); ?>);
                            return;
                        }

                        if (event.source && typeof event.source.close === 'function') {
                            event.source.close();
                        }
                        currentNonce = null;

                        fetch(<?php echo wp_json_encode($adminAjaxUrl); ?>, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                action: 'kenzi_save_connection',
                                _wpnonce: <?php echo wp_json_encode($saveNonce); ?>,
                                workspace_id: event.data.workspace_id || '',
                                workspace_name: event.data.workspace_name || '',
                                secret: event.data.secret || '',
                                integration_id: String(event.data.integration_id || '')
                            })
                        })
                        .then(r => r.json())
                        .then(result => {
                            if (result.success) {
                                window.location.href = <?php echo wp_json_encode($settingsUrl . '&connected=1'); ?>;
                            } else {
                                alert(<?php echo wp_json_encode(__('Failed to save connection:', 'kenzi-chat')); ?> + ' ' + (result.data || 'Unknown error'));
                            }
                        })
                        .catch(() => alert(<?php echo wp_json_encode(__('Failed to save connection. Please try again.', 'kenzi-chat')); ?>));
                    }
                });
            })();
            </script>
        </div>
        <?php
    }
}
