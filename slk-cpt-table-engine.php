<?php

/**
 * Plugin Name: SLK CPT Table Engine
 * Plugin URI: https://slk-communications.de/plugins/cpt-table-engine/
 * Description: Optimizes database performance by storing Custom Post Types in dedicated custom tables instead of wp_posts and wp_postmeta.
 * Version: 1.1.0
 * Author: Sebastian Klaus
 * Author URI: https://slk-communications.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: slk-cpt-table-engine
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.1
 *
 * @package SLK\CptTableEngine
 */

declare(strict_types=1);

namespace SLK\CptTableEngine;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

define('SLK_PLUGIN_NAME', 'CPT Table Engine');
define('SLK_DEBUG', true); // Set to false in production

// Define plugin constants.
define('CPT_TABLE_ENGINE_VERSION', '1.0.0');
define('CPT_TABLE_ENGINE_FILE', __FILE__);
define('CPT_TABLE_ENGINE_PATH', plugin_dir_path(__FILE__));
define('CPT_TABLE_ENGINE_URL', plugin_dir_url(__FILE__));
define('CPT_TABLE_ENGINE_BASENAME', plugin_basename(__FILE__));
define('CPT_TABLE_ENGINE_TEXT_DOMAIN', 'slk-cpt-table-engine');

if (file_exists(__DIR__ . '/modules/UpdateChecker/check.php')) {
    require_once __DIR__ . '/modules/UpdateChecker/check.php';
}

// Composer autoloader.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function init(): void
{
    Plugin::instance()->init();
}

/**
 * Load the plugin text domain.
 *
 * @return void
 */
function load_textdomain(): void
{
    load_plugin_textdomain(
        CPT_TABLE_ENGINE_TEXT_DOMAIN,
        false,
        dirname(CPT_TABLE_ENGINE_BASENAME) . '/languages'
    );
}

/**
 * Plugin activation hook.
 *
 * @return void
 */
function activate(): void
{
    Plugin::instance()->activate();
}

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function deactivate(): void
{
    Plugin::instance()->deactivate();
}

// Register activation and deactivation hooks.
register_activation_hook(CPT_TABLE_ENGINE_FILE, __NAMESPACE__ . '\\activate');
register_deactivation_hook(CPT_TABLE_ENGINE_FILE, __NAMESPACE__ . '\\deactivate');

// Initialize the plugin.
add_action('plugins_loaded', __NAMESPACE__ . '\\init', 0);
add_action('init', __NAMESPACE__ . '\\load_textdomain', 5);
