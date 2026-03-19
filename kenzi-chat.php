<?php

/**
 * Plugin Name: Kenzi Chat
 * Plugin URI:  https://kenzi.chat
 * Description: Add the Kenzi chat widget to your WordPress site for customer messaging.
 * Version:     1.0.0
 * Author:      Kenzi
 * Author URI:  https://kenzi.chat
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kenzi-chat
 * Requires at least: 6.3
 * Requires PHP: 8.1
 *
 * @package Kenzi\Chat
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('KENZI_CHAT_VERSION', '1.0.0');
define('KENZI_CHAT_PLUGIN_FILE', __FILE__);

require_once __DIR__ . '/vendor/autoload.php';

use Kenzi\Chat\Plugin;

add_action('plugins_loaded', static function (): void {
    Plugin::instance()->init();
});

register_deactivation_hook(__FILE__, static function (): void {
    delete_transient('kenzi_chat_notice');
});
