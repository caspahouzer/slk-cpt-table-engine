<?php

declare(strict_types=1);

namespace SLK\CptTableEngine;

use SLK\CptTableEngine\Admin\SettingsPage;
use SLK\CptTableEngine\Utilities\Logger;
use SLK\CptTableEngine\Admin\AjaxHandler;
use SLK\CptTableEngine\Admin\DeactivationGuard;
use SLK\CptTableEngine\Admin\TableAdminNotices;
use SLK\CptTableEngine\Services\Integration\QueryInterceptor;
use SLK\CptTableEngine\Services\Integration\CrudInterceptor;
use SLK\CptTableEngine\Services\Database\TableManager;
use SLK\CptTableEngine\Services\Database\TableSchema;

/**
 * Plugin class.
 *
 * @package SLK\CptTableEngine
 */
final class Plugin
{
    /**
     * Singleton instance.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Settings page instance.
     *
     * @var SettingsPage|null
     */
    private ?SettingsPage $settings_page = null;

    /**
     * AJAX handler instance.
     *
     * @var AjaxHandler|null
     */
    private ?AjaxHandler $ajax_handler = null;

    /**
     * Query interceptor instance.
     *
     * @var QueryInterceptor|null
     */
    private ?QueryInterceptor $query_interceptor = null;

    /**
     * CRUD interceptor instance.
     *
     * @var CrudInterceptor|null
     */
    private ?CrudInterceptor $crud_interceptor = null;

    /**
     * Deactivation guard instance.
     *
     * @var DeactivationGuard|null
     */
    private ?DeactivationGuard $deactivation_guard = null;

    /**
     * Table admin notices instance.
     *
     * @var TableAdminNotices|null
     */
    private ?TableAdminNotices $table_admin_notices = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {}

    /**
     * Get singleton instance.
     *
     * @return Plugin
     */
    public static function instance(): Plugin
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
        // Initialize frontend components.
        $this->init_frontend();

        // Initialize admin components.
        if (is_admin()) {
            $this->init_admin();
        }
    }

    /**
     * Initialize admin components.
     *
     * @return void
     */
    private function init_admin(): void
    {
        $this->settings_page = new SettingsPage();
        $this->ajax_handler  = new AjaxHandler();
        $this->deactivation_guard = new DeactivationGuard();
        $this->table_admin_notices = new TableAdminNotices();

        // Initialize License Manager.
        if (class_exists('\SLK\LicenseChecker\LicenseChecker')) {
            \SLK\LicenseChecker\LicenseChecker::instance();
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
        $this->query_interceptor = new QueryInterceptor();
        $this->crud_interceptor  = new CrudInterceptor();
    }

    /**
     * Re-initialize frontend components.
     *
     * @return void
     */
    public function reinit(): void
    {
        $this->init_frontend();
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

        if (!\SLK\LicenseChecker\LicenseChecker::is_active()) {
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

        // Set default table handling mode if not exists.
        if (false === get_option('cpt_table_engine_table_handling_mode')) {
            add_option('cpt_table_engine_table_handling_mode', 'auto', '', 'no');
        }

        // Detect existing tables.
        $this->detect_and_handle_existing_tables();

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
        DeactivationGuard::prevent_deactivation();

        // Flush rewrite rules.
        flush_rewrite_rules();

        // Clear all plugin-related transients.
        $this->clear_transients();
    }

    /**
     * Detect and handle existing CPT tables on activation.
     *
     * @return void
     */
    private function detect_and_handle_existing_tables(): void
    {
        Logger::info('Checking for existing CPT tables during activation...');

        // Detect all existing CPT tables.
        $existing_tables = TableManager::detect_existing_tables();

        if (empty($existing_tables)) {
            Logger::info('No existing CPT tables detected.');
            TableAdminNotices::store_activation_results([
                'existing_tables'   => [],
                'tables_with_data'  => [],
                'validated_tables'  => [],
            ]);
            return;
        }

        Logger::info(sprintf('Found %d existing CPT table(s).', count($existing_tables)));

        $tables_with_data  = [];
        $validated_tables  = [];
        $handling_mode     = get_option('cpt_table_engine_table_handling_mode', 'auto');

        foreach ($existing_tables as $table_name => $table_info) {
            // Track tables with data.
            if ($table_info['has_data']) {
                $tables_with_data[] = $table_name;
            }

            // Validate schema if mode is 'validate' or 'backup'.
            if (in_array($handling_mode, ['validate', 'backup'], true)) {
                $validation = TableSchema::validate_table_schema(
                    $table_name,
                    $table_info['type']
                );

                $validated_tables[$table_name] = $validation;

                if (!$validation['valid']) {
                    Logger::warning(
                        "Schema mismatch for {$table_name}: {$validation['message']}"
                    );

                    // Add warning notice for invalid schema.
                    TableAdminNotices::add_schema_warning(
                        $table_name,
                        $validation['message']
                    );
                }
            }

            // Create backup if mode is 'backup' and table has data.
            if ('backup' === $handling_mode && $table_info['has_data']) {
                $backup_name = TableManager::backup_table($table_name);
                if ($backup_name) {
                    Logger::info("Created backup: {$backup_name}");
                } else {
                    Logger::error("Failed to create backup for: {$table_name}");
                }
            }
        }

        // Store results for admin notice display.
        TableAdminNotices::store_activation_results([
            'existing_tables'   => array_keys($existing_tables),
            'tables_with_data'  => $tables_with_data,
            'validated_tables'  => $validated_tables,
            'handling_mode'     => $handling_mode,
        ]);

        Logger::info('Existing table detection and handling complete.');
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
