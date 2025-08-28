<?php
/**
 * Plugin Name: Disable Admin Ad & ad blocker
 * Description: Hides HTML ad blocks in the WordPress admin panel using saved XPath rules. Enables element selection mode, output buffering, and settings.
 * Author: Alex Kovalevv
 * Version: 1.0.18
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * Text Domain: disable-admin-ad
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
const AIDAD_PLUGIN_FILE = __FILE__;
const AIDAD_PLUGIN_DIR = __DIR__;
const AIDAD_PLUGIN_VERSION = '1.0.18';

if (!defined('AIDAD_PLUGIN_URL')) {
    define('AIDAD_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Load Composer autoloader.
$autoload = AIDAD_PLUGIN_DIR . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    // Fallback simple PSR-4 autoloader for development before composer install.
    spl_autoload_register(static function ($class) {
        $prefix = 'AIDAD\\';
        $base_dir = __DIR__ . '/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use AIDAD\Core\Plugin;

/**
 * Bootstrap the plugin on plugins_loaded.
 */
function aidad_bootstrap_plugin(): void
{
    $plugin = new Plugin();
    $plugin->run();
}

add_action('plugins_loaded', 'aidad_bootstrap_plugin');

// Since WP 4.6, WordPress auto-loads translations for plugins from wp.org.
// If this plugin is distributed elsewhere, consider loading MO files manually.
// We intentionally avoid load_plugin_textdomain() to satisfy Plugin Check recommendations.

/**
 * Activation hook: initialize default options and run migrations.
 */
function aidad_on_activate(): void
{
    if (!function_exists('is_multisite')) {
        return;
    }
    $container = (new Plugin())->get_container();
    $options_repo = $container->get('options_repository');
    $options_repo->ensure_defaults();
}

register_activation_hook(__FILE__, 'aidad_on_activate');

