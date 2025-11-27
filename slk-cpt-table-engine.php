<?php

/**
 * Plugin Name: SLK CPT Table Engine
 * Plugin URI: https://slk-communications.de/plugins/cpt-table-engine/
 * Description: Optimizes database performance by storing Custom Post Types in dedicated custom tables instead of wp_posts and wp_postmeta.
 * Version: 1.1.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Sebastian Klaus
 * Author URI: https://slk-communications.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: slk-cpt-table-engine
 * Domain Path: /languages
 *
 * @package SLK\Cpt_Table_Engine
 */

declare(strict_types=1);

namespace SLK\Cpt_Table_Engine;

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

/**
 * PSR-4 Autoloader for the plugin.
 * 
 * Supports multiple namespace prefixes:
 * - SLK_Cpt_Table_Engine -> /includes/
 * - SLK -> /modules/
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register(function (string $class): void {
    // Define namespace to directory mappings.
    $namespace_mappings = [
        'SLK\\Cpt_Table_Engine\\' => CPT_TABLE_ENGINE_PATH . 'includes/',
        'SLK\\'                   => CPT_TABLE_ENGINE_PATH . 'modules/',
    ];

    // Try each namespace prefix.
    foreach ($namespace_mappings as $prefix => $base_dir) {
        $len = strlen($prefix);

        // Check if class uses this namespace prefix.
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        // Get the relative class name.
        $relative_class = substr($class, $len);

        // Split namespace and class name.
        $parts = explode('\\', $relative_class);
        $class_name = array_pop($parts);

        // Convert class name to file name (License_Checker -> class-license-checker).
        $file_name = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';

        // Build the namespace path (License_Checker -> license-checker/).
        $namespace_path = '';
        if (!empty($parts)) {
            $converted_parts = array_map(function ($part) {
                return strtolower(str_replace('_', '-', $part));
            }, $parts);
            $namespace_path = implode('/', $converted_parts) . '/';
        }

        $file = $base_dir . $namespace_path . $file_name;

        // If file exists, require it and return.
        if (file_exists($file)) {
            require $file;
            return;
        }
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

// --- TEMPORARY FULL API TEST SCRIPT ---
add_action('admin_init', function() {
    if (isset($_GET['slk_full_api_test']) && $_GET['slk_full_api_test'] === '1' && current_user_can('manage_options')) {
        $license_key = 'slk-S386-DAJG-F3BG-28Y1';
        $invalid_license_key = 'slk-S386-DAJG-F3BG-28Y2'; // Changed last digit

        // Set a longer execution time in case the API is slow.
        @set_time_limit(300);

        echo '<pre>';

        // 1. Activate License
        echo '<h2>1. Activating License...</h2>';
        $activation_result = \SLK\License_Checker\License_Helper::activate_license($license_key);
        print_r($activation_result);
        echo '<hr>';

        // 1.5. Activate License Again (to check for errors)
        echo '<h2>1.5. Activating License Again...</h2>';
        $activation_result_2 = \SLK\License_Checker\License_Helper::activate_license($license_key);
        print_r($activation_result_2);
        echo '<hr>';

        // Extract activation hash for deactivation
        $activation_hash = $activation_result['activation_hash'] ?? null;

        // 2. Get Details (after activation)
        echo '<h2>2. Getting License Details (after activation)...</h2>';
        $details_after_activation = \SLK\License_Checker\License_Helper::get_license_details($license_key);
        print_r($details_after_activation);
        echo '<hr>';

        // 3. Deactivate License
        echo '<h2>3. Deactivating License...</h2>';
        if ($activation_hash) {
            // Add a small delay to ensure the server can process the activation before deactivation.
            sleep(1);
            $deactivation_result = \SLK\License_Checker\License_Helper::deactivate_license($license_key, $activation_hash);
            print_r($deactivation_result);
        } else {
            echo 'Skipping deactivation because no activation hash was found in the activation response.';
        }
        echo '<hr>';

        // 4. Get Details (after deactivation)
        echo '<h2>4. Getting License Details (after deactivation)...</h2>';
        // Add another small delay.
        sleep(1);
        $details_after_deactivation = \SLK\License_Checker\License_Helper::get_license_details($license_key);
        print_r($details_after_deactivation);
        echo '<hr>';
        
        // 5. Attempt to activate an invalid license key
        echo '<h2>5. Activating Invalid License...</h2>';
        $invalid_activation_result = \SLK\License_Checker\License_Helper::activate_license($invalid_license_key);
        print_r($invalid_activation_result);

        echo '</pre>';
        die();
    }
});
