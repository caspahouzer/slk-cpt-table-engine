<?php

/**
 * Bootstrap class for CPT Table Engine.
 *
 * Handles plugin initialization, component registration, and hook setup.
 *
 * @package SLK_Cpt_Table_Engine
 */

declare(strict_types=1);

namespace SLK\Cpt_Table_Engine;

use SLK\Cpt_Table_Engine\Admin\Settings_Page;
use SLK\Cpt_Table_Engine\Admin\Ajax_Handler;
use SLK\Cpt_Table_Engine\Admin\Deactivation_Guard;
use SLK\Cpt_Table_Engine\Integration\Query_Interceptor;
use SLK\Cpt_Table_Engine\Integration\CRUD_Interceptor;

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
        // Initialize admin components.
        if (is_admin()) {
            $this->init_admin();
        }

        // Initialize frontend components.
        $this->init_frontend();
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

        // Initialize License Manager.
        if (class_exists('\SLK\License_Manager\License_Manager')) {
            \SLK\License_Manager\License_Manager::instance();
            add_action('admin_notices', [$this, 'show_license_inactive_notice']);
        }
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
     * Show admin notice if license is not active.
     *
     * @return void
     */
    public function show_license_inactive_notice(): void
    {
        // Don't show on the plugin's own settings page.
        // Nonce verification is not required here because we are only reading the 'page' parameter
        // to conditionally display an admin notice. This is a non-destructive read operation.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($page === 'slk-cpt-table-engine') {
            return;
        }

        if (!\SLK\License_Manager\License_Manager::is_active()) {
            $settings_url = admin_url('options-general.php?page=slk-cpt-table-engine&tab=license');
?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php echo esc_html(SLK_PLUGIN_NAME); ?>:</strong>
                    <?php
                    printf(
                        /* translators: %s: URL to license settings page */
                        wp_kses_post(__('Please <a href="%s">activate your license</a> to receive plugin updates and support.', 'slk-cpt-table-engine')),
                        esc_url($settings_url)
                    );
                    ?>
                </p>
            </div>
<?php
        }
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
