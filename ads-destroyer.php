<?php
/**
 * Plugin Name: AdsDestroyer - disable admin ad & adblocker
 * Description: Hides HTML ad blocks in the WordPress admin panel using saved XPath rules. Enables element selection mode, output buffering, and settings.
 * Author: wpaifactory
 * Author URI: https://wp-aifactory.com
 * Version: 1.0.21
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * Text Domain: ads-destroyer
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
const ADSD_PLUGIN_FILE = __FILE__;
const ADSD_PLUGIN_DIR = __DIR__;
const ADSD_PLUGIN_VERSION = '1.0.21';

if (!defined('ADSD_PLUGIN_URL')) {
    define('ADSD_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Load Composer autoloader.
$autoload = ADSD_PLUGIN_DIR . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    // Fallback simple PSR-4 autoloader for development before composer install.
    spl_autoload_register(static function ($class) {
        $prefix = 'ADSD\\';
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

use ADSD\Core\Plugin;

/**
 * Bootstrap the plugin on plugins_loaded.
 */
function adsd_bootstrap_plugin(): void
{
    $plugin = new Plugin();
    $plugin->run();
}

add_action('plugins_loaded', 'adsd_bootstrap_plugin');

// Since WP 4.6, WordPress auto-loads translations for plugins from wp.org.
// If this plugin is distributed elsewhere, consider loading MO files manually.
// We intentionally avoid load_plugin_textdomain() to satisfy Plugin Check recommendations.

/**
 * Activation hook: initialize default options and run migrations.
 */
function adsd_on_activate(): void
{
    if (!function_exists('is_multisite')) {
        return;
    }
    $container = (new Plugin())->get_container();
    $options_repo = $container->get('options_repository');
    $options_repo->ensure_defaults();
}

register_activation_hook(__FILE__, 'adsd_on_activate');

