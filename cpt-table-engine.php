<?php

/**
 * Plugin Name: CPT Table Engine
 * Plugin URI: https://github.com/caspahouzer/cpt-table-engine
 * Description: Optimizes database performance by storing Custom Post Types in dedicated custom tables instead of wp_posts and wp_postmeta.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Sebastian Klaus
 * Author URI: https://slk-communications.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cpt-table-engine
 * Domain Path: /languages
 *
 * @package CPT_Table_Engine
 */

declare(strict_types=1);

namespace CPT_Table_Engine;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('CPT_TABLE_ENGINE_VERSION', '1.0.0');
define('CPT_TABLE_ENGINE_FILE', __FILE__);
define('CPT_TABLE_ENGINE_PATH', plugin_dir_path(__FILE__));
define('CPT_TABLE_ENGINE_URL', plugin_dir_url(__FILE__));
define('CPT_TABLE_ENGINE_BASENAME', plugin_basename(__FILE__));
define('CPT_TABLE_ENGINE_TEXT_DOMAIN', 'cpt-table-engine');

/**
 * PSR-4 Autoloader for the plugin.
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'CPT_Table_Engine\\';
    $base_dir = CPT_TABLE_ENGINE_PATH . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);

    // Split namespace and class name.
    $parts = explode('\\', $relative_class);
    $class_name = array_pop($parts);

    // Convert class name to file name (Settings_Page -> class-settings-page).
    $file_name = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';

    // Build the full path.
    $namespace_path = !empty($parts) ? implode('/', array_map('strtolower', $parts)) . '/' : '';
    $file = $base_dir . $namespace_path . $file_name;

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Initialize the plugin.
 *
 * @return void
 */
function init(): void
{
    require_once CPT_TABLE_ENGINE_PATH . 'includes/bootstrap.php';
    Bootstrap::instance()->init();
}

/**
 * Plugin activation hook.
 *
 * @return void
 */
function activate(): void
{
    require_once CPT_TABLE_ENGINE_PATH . 'includes/bootstrap.php';
    Bootstrap::instance()->activate();
}

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function deactivate(): void
{
    require_once CPT_TABLE_ENGINE_PATH . 'includes/bootstrap.php';
    Bootstrap::instance()->deactivate();
}

// Register activation and deactivation hooks.
register_activation_hook(CPT_TABLE_ENGINE_FILE, __NAMESPACE__ . '\\activate');
register_deactivation_hook(CPT_TABLE_ENGINE_FILE, __NAMESPACE__ . '\\deactivate');

// Initialize the plugin.
add_action('plugins_loaded', __NAMESPACE__ . '\\init');
