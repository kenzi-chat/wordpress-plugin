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

    // Prevent cloning and unserialization of the singleton.
    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize singleton');
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
     * Register admin settings page and form handler.
     */
    private function registerSettings(): void
    {
        $settingsPage = new Admin\SettingsPage();

        add_action('admin_menu', [$settingsPage, 'register']);

        // handleSave must run on admin_init (fires before admin_menu).
        add_action('admin_init', [$settingsPage, 'handleSave']);

        // Enqueue admin JS/CSS only on the Kenzi Chat settings page.
        add_action('admin_enqueue_scripts', [$settingsPage, 'enqueueAdminAssets']);
    }

    /**
     * Register AJAX handlers for connection save and disconnect.
     */
    private function registerAjaxHandlers(): void
    {
        Admin\Ajax::register();
    }

    /**
     * Register widget script injection on frontend pages.
     */
    private function registerWidgetInjection(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueWidget']);
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

        add_filter('plugin_row_meta', static function (array $meta, string $file) use ($pluginBasename): array {
            if ($file !== $pluginBasename) {
                return $meta;
            }

            $meta[] = '<a href="' . esc_url('https://wiki.kenzi.chat/integrations/wordpress/') . '">' . __('Docs', 'kenzi-chat') . '</a>';

            return $meta;
        }, 10, 2);
    }

    /**
     * Enqueue the Kenzi widget loader script on frontend pages.
     */
    public function enqueueWidget(): void
    {
        if (! Settings::isWidgetEnabled()) {
            return;
        }

        $widgetUrl = Settings::getWidgetUrl();

        if ($widgetUrl === null) {
            return;
        }

        wp_enqueue_script(
            'kenzi-widget',
            $widgetUrl,
            [],
            KENZI_CHAT_VERSION,
            ['in_footer' => true, 'strategy' => 'defer'],
        );
    }
}
