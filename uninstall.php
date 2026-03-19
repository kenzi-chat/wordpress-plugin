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
 * @see https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 *
 * @package Kenzi\Chat
 */

// Abort if not called by WordPress during uninstall.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove connection settings.
delete_option('kenzi_workspace_id');
delete_option('kenzi_shared_secret');
delete_option('kenzi_integration_id');
delete_option('kenzi_widget_enabled');

// Remove capabilities.
delete_option('kenzi_capabilities');
