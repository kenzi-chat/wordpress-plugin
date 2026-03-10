<?php

declare(strict_types=1);

namespace Kenzi\Chat;

/**
 * Main plugin class.
 *
 * Singleton that orchestrates all plugin functionality:
 * - Admin settings page registration
 * - AJAX handler registration
 * - Widget script injection
 * - Plugin action links
 */
final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    /**
     * Initialize the plugin.
     *
     * Called on `plugins_loaded`.
     */
    public function init(): void
    {
        $this->registerSettings();
        $this->registerAjaxHandlers();
        $this->registerWidgetInjection();
        $this->registerPluginLinks();
    }

    /**
     * Register admin settings page.
     */
    private function registerSettings(): void
    {
        add_action('admin_menu', static function (): void {
            (new Admin\SettingsPage())->register();
        });
    }

    /**
     * Register AJAX handlers for connection save/reset.
     */
    private function registerAjaxHandlers(): void
    {
        Admin\Ajax::register();
    }

    /**
     * Register widget script injection on frontend.
     */
    private function registerWidgetInjection(): void
    {
        add_action('wp_footer', [$this, 'injectWidget']);
    }

    /**
     * Register plugin action links (Settings link on plugins page).
     */
    private function registerPluginLinks(): void
    {
        $pluginBasename = plugin_basename(KENZI_CHAT_PLUGIN_FILE);

        add_filter('plugin_action_links_' . $pluginBasename, static function (array $links): array {
            $settingsUrl = admin_url('admin.php?page=kenzi-chat');
            $settingsLink = '<a href="' . esc_url($settingsUrl) . '">' . __('Settings', 'kenzi-chat') . '</a>';

            array_unshift($links, $settingsLink);

            return $links;
        });
    }

    /**
     * Inject the Kenzi widget script into frontend pages.
     *
     * Only injects if widget is enabled and widget URL is configured.
     */
    public function injectWidget(): void
    {
        if (is_admin()) {
            return;
        }

        if (! Settings::isWidgetEnabled()) {
            return;
        }

        $widgetUrl = Settings::getWidgetUrl();

        if ($widgetUrl === null) {
            return;
        }

        echo '<script src="' . esc_url($widgetUrl) . '"></script>' . "\n";
    }

    // TODO: Webhook support
    // public function removeWebhooks(): void
    // {
    //     // Remove registered webhooks on plugin deactivation
    // }
}
