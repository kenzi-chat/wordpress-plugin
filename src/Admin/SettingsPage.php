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
final class SettingsPage
{
    private const PAGE_SLUG = 'kenzi-chat';

    /**
     * Register the admin menu page.
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
    }

    /**
     * Handle widget settings form submission.
     */
    public function handleSave(): void
    {
        if (! isset($_POST['kenzi_save_widget'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'kenzi-chat'), '', ['response' => 403]);
        }

        if ($_POST['kenzi_save_widget'] === '1') {
            if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_kenzi_widget_nonce'] ?? '')), 'kenzi_save_widget_settings')) {
                wp_die(esc_html__('Security check failed.', 'kenzi-chat'), '', ['response' => 403]);
            }

            Settings::setWidgetEnabled(isset($_POST['widget_enabled']));

            set_transient('kenzi_chat_notice', 'updated', 30);
            wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
            exit;
        }
    }

    /**
     * Enqueue admin scripts and styles for this settings page.
     */
    public function enqueueAdminAssets(string $hookSuffix): void
    {
        // Only load on this plugin's settings page.
        if ($hookSuffix !== 'toplevel_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style(
            'kenzi-chat-admin',
            plugins_url('assets/css/admin.css', KENZI_CHAT_PLUGIN_FILE),
            [],
            KENZI_CHAT_VERSION,
        );

        wp_enqueue_script(
            'kenzi-chat-admin',
            plugins_url('assets/js/admin-connect.js', KENZI_CHAT_PLUGIN_FILE),
            [],
            KENZI_CHAT_VERSION,
            ['in_footer' => true],
        );

        wp_localize_script('kenzi-chat-admin', 'kenziChatAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'connectUrl' => Settings::getConnectUrl(),
            'storeUrl' => home_url(),
            'instanceKey' => Settings::getInstanceKey(),
            'adminUrl' => admin_url(),
            'settingsUrl' => admin_url('admin.php?page=' . self::PAGE_SLUG),
            'capabilities' => Settings::detectCapabilities(),
            'nonces' => [
                'save' => wp_create_nonce('kenzi_save_connection'),
                'disconnect' => wp_create_nonce('kenzi_disconnect'),
            ],
            // Translatable strings for the connect/disconnect popup flow.
            'i18n' => [
                'popupBlocked' => __('Popup was blocked. Please allow popups for this site.', 'kenzi-chat'),
                'confirmDisconnect' => __('Disconnect from Kenzi? This will remove the workspace connection.', 'kenzi-chat'),
                'securityFailed' => __('Security validation failed. Please try again.', 'kenzi-chat'),
                'disconnectFailed' => __('Disconnect failed:', 'kenzi-chat'),
                'disconnectFailedRetry' => __('Disconnect failed. Please try again.', 'kenzi-chat'),
                'saveFailed' => __('Failed to save connection:', 'kenzi-chat'),
                'saveFailedRetry' => __('Failed to save connection. Please try again.', 'kenzi-chat'),
            ],
        ]);
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

        // Transient-based notices — deleted after first read to prevent stale display.
        $notice = get_transient('kenzi_chat_notice');
        $connectedName = get_transient('kenzi_chat_connected_name');
        if ($notice !== false) {
            delete_transient('kenzi_chat_notice');
        }
        if ($connectedName !== false) {
            delete_transient('kenzi_chat_connected_name');
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Kenzi Chat', 'kenzi-chat'); ?></h1>

            <?php if ($notice === 'updated'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved.', 'kenzi-chat'); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($notice === 'connected'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php if ($connectedName): ?>
                            <?php
                            printf(
                                /* translators: %s: workspace name */
                                esc_html__('Successfully connected to %s!', 'kenzi-chat'),
                                '<strong>' . esc_html($connectedName) . '</strong>'
                            );
                            ?>
                        <?php else: ?>
                            <?php esc_html_e('Successfully connected to Kenzi!', 'kenzi-chat'); ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e('Connection', 'kenzi-chat'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Workspace', 'kenzi-chat'); ?></th>
                    <td>
                        <?php if ($isConnected): ?>
                            <p class="kenzi-status-connected">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e('Connected', 'kenzi-chat'); ?>
                            </p>
                            <button type="button" class="button" onclick="kenziConnect()">
                                <?php esc_html_e('Reconnect', 'kenzi-chat'); ?>
                            </button>
                            <button type="button" class="button kenzi-btn-disconnect" onclick="kenziDisconnect()">
                                <?php esc_html_e('Disconnect', 'kenzi-chat'); ?>
                            </button>
                        <?php else: ?>
                            <p class="kenzi-status-prompt">
                                <?php esc_html_e('Connect your site to a Kenzi workspace to enable chat.', 'kenzi-chat'); ?>
                            </p>
                            <button type="button" onclick="kenziConnect()" class="kenzi-btn-connect">
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
                                    <span class="kenzi-widget-disabled-hint">
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
        </div>
        <?php
    }
}
