<?php

/**
 * Bootstrap class for CPT Table Engine.
 *
 * Handles plugin initialization, component registration, and hook setup.
 *
 * @package CPT_Table_Engine
 */

declare(strict_types=1);

namespace CPT_Table_Engine;

use CPT_Table_Engine\Admin\Settings_Page;
use CPT_Table_Engine\Admin\Ajax_Handler;
use CPT_Table_Engine\Admin\Deactivation_Guard;
use CPT_Table_Engine\Integration\Query_Interceptor;
use CPT_Table_Engine\Integration\CRUD_Interceptor;

/**
 * Bootstrap class.
 */
final class Bootstrap
{
    /**
     * Singleton instance.
     *
     * @var Bootstrap|null
     */
    private static ?Bootstrap $instance = null;

    /**
     * Settings page instance.
     *
     * @var Settings_Page|null
     */
    private ?Settings_Page $settings_page = null;

    /**
     * AJAX handler instance.
     *
     * @var Ajax_Handler|null
     */
    private ?Ajax_Handler $ajax_handler = null;

    /**
     * Query interceptor instance.
     *
     * @var Query_Interceptor|null
     */
    private ?Query_Interceptor $query_interceptor = null;

    /**
     * CRUD interceptor instance.
     *
     * @var CRUD_Interceptor|null
     */
    private ?CRUD_Interceptor $crud_interceptor = null;

    /**
     * Deactivation guard instance.
     *
     * @var Deactivation_Guard|null
     */
    private ?Deactivation_Guard $deactivation_guard = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {}

    /**
     * Get singleton instance.
     *
     * @return Bootstrap
     */
    public static function instance(): Bootstrap
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize the plugin.
     *
     * @return void
     */
    public function init(): void
    {
        // Load text domain for internationalization.
        add_action('init', [$this, 'load_textdomain']);

        // Initialize admin components.
        if (is_admin()) {
            $this->init_admin();
        }

        // Initialize frontend components.
        $this->init_frontend();
    }

    /**
     * Load plugin text domain for translations.
     *
     * @return void
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            CPT_TABLE_ENGINE_TEXT_DOMAIN,
            false,
            dirname(CPT_TABLE_ENGINE_BASENAME) . '/languages'
        );
    }

    /**
     * Initialize admin components.
     *
     * @return void
     */
    private function init_admin(): void
    {
        $this->settings_page = new Settings_Page();
        $this->ajax_handler  = new Ajax_Handler();
        $this->deactivation_guard = new Deactivation_Guard();
    }

    /**
     * Initialize frontend components.
     *
     * @return void
     */
    private function init_frontend(): void
    {
        $this->query_interceptor = new Query_Interceptor();
        $this->crud_interceptor  = new CRUD_Interceptor();
    }

    /**
     * Plugin activation callback.
     *
     * @return void
     */
    public function activate(): void
    {
        // Set default options.
        if (false === get_option('cpt_table_engine_enabled_cpts')) {
            add_option('cpt_table_engine_enabled_cpts', [], '', 'no');
        }

        // Store plugin version.
        update_option('cpt_table_engine_version', CPT_TABLE_ENGINE_VERSION);

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation callback.
     *
     * @return void
     */
    public function deactivate(): void
    {
        // Prevent deactivation if CPTs are still enabled.
        Deactivation_Guard::prevent_deactivation();

        // Flush rewrite rules.
        flush_rewrite_rules();

        // Clear all plugin-related transients.
        $this->clear_transients();
    }

    /**
     * Clear all plugin-related transients.
     *
     * @return void
     */
    private function clear_transients(): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_cpt_table_engine_') . '%',
                $wpdb->esc_like('_transient_timeout_cpt_table_engine_') . '%'
            )
        );
    }

    /**
     * Prevent cloning.
     *
     * @return void
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     *
     * @return void
     */
    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
