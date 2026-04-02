<?php

declare(strict_types=1);

/**
 * Kenzi Chat uninstall handler.
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes all plugin options from the database so that no
 * sensitive data (e.g. the shared secret) persists after the
 * user has chosen to remove the plugin.
 *
 * On multisite, options are deleted from every subsite — not
 * just the main site — because each subsite stores its own
 * independent connection state via `get_option()`.
 *
 * @see https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 *
 * @package Kenzi\Chat
 */

// Abort if not called by WordPress during uninstall.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete all Kenzi Chat options for the current site.
 */
function kenzi_chat_delete_site_options(): void
{
    delete_option('kenzi_workspace_id');
    delete_option('kenzi_shared_secret');
    delete_option('kenzi_integration_id');
    delete_option('kenzi_widget_enabled');
    delete_option('kenzi_capabilities');
}

if (is_multisite()) {
    $sites = get_sites(['fields' => 'ids']);

    foreach ($sites as $blog_id) {
        switch_to_blog($blog_id);
        kenzi_chat_delete_site_options();
        restore_current_blog();
    }
} else {
    kenzi_chat_delete_site_options();
}
