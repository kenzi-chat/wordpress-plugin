<?php

declare(strict_types=1);

namespace Kenzi\Chat\Admin;

use Kenzi\Chat\Settings;

/**
 * Admin settings page for Kenzi Chat.
 *
 * The connection section is rendered client-side from a live
 * GET /kenzi/integration call — see admin-connect.js and §9.
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

        $supportedGrants = Settings::detectGrants();

        wp_localize_script('kenzi-chat-admin', 'kenziChatAdmin', [
            'connectUrl' => Settings::getConnectUrl(),
            'restBase' => rtrim(rest_url(), '/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'instanceKey' => Settings::getInstanceKey(),
            'platformType' => 'wordpress',
            'supportedGrants' => implode(',', $supportedGrants),
            'settingsUrl' => admin_url('admin.php?page=' . self::PAGE_SLUG),
            'isConnected' => Settings::isConnected(),
            'hasWooCommerce' => in_array('commerce', $supportedGrants, true),
            'widgetEnabled' => Settings::isWidgetEnabled(),
            'widgetNonce' => wp_create_nonce('kenzi_save_widget_settings'),
            'i18n' => [
                'popupBlocked' => __('Popup was blocked. Please allow popups for this site.', 'kenzi-chat'),
                'confirmDisconnect' => __('Disconnect from Kenzi? This will remove the workspace connection.', 'kenzi-chat'),
                'disconnectFailed' => __('Disconnect failed. Please try again.', 'kenzi-chat'),
                'somethingWrong' => __('Something went wrong with your Kenzi connection.', 'kenzi-chat'),
                'disconnect' => __('Disconnect', 'kenzi-chat'),
                'connecting' => __('Connecting…', 'kenzi-chat'),
                'connectPrompt' => __('Connect your site to a Kenzi workspace to enable chat.', 'kenzi-chat'),
                'connectButton' => __('Connect to Kenzi', 'kenzi-chat'),
                'connectedTo' => __('Connected to %s', 'kenzi-chat'),
                'unknownStatus' => __('Kenzi reports status: %s. See documentation.', 'kenzi-chat'),
                'enableCommerce' => __('Enable Commerce', 'kenzi-chat'),
                'unreachable' => __('Could not reach Kenzi. Please try again later.', 'kenzi-chat'),
                'connectionReset' => __('Your Kenzi connection was reset. Please reconnect.', 'kenzi-chat'),
                'widgetLabel' => __('Show Kenzi chat widget on all pages', 'kenzi-chat'),
                'widgetHint' => __('When enabled, the chat widget appears on all pages.', 'kenzi-chat'),
                'widgetHintDisabled' => __('Connect to Kenzi first to enable the widget.', 'kenzi-chat'),
                'saveChanges' => __('Save Changes', 'kenzi-chat'),
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

        // Transient-based notices — deleted after first read to prevent stale display.
        $notice = get_transient('kenzi_chat_notice');
        if ($notice !== false) {
            delete_transient('kenzi_chat_notice');
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Kenzi Chat', 'kenzi-chat'); ?></h1>

            <?php if ($notice === 'updated'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved.', 'kenzi-chat'); ?></p>
                </div>
            <?php endif; ?>

            <div id="kenzi-settings">
                <p class="description"><?php esc_html_e('Loading…', 'kenzi-chat'); ?></p>
            </div>
        </div>
        <?php
    }
}
