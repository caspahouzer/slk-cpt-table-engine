<?php

declare(strict_types=1);

namespace SLK\CptTableEngine\Admin;

use SLK\CptTableEngine\Utilities\Logger;
use SLK\CptTableEngine\Controllers\SettingsController;
use SLK\LicenseChecker\LicenseChecker;

/**
 * Table Admin Notices class.
 *
 * @package SLK\CptTableEngine
 */
final class TableAdminNotices
{
    /**
     * Transient key for activation results.
     */
    private const ACTIVATION_RESULTS_TRANSIENT = 'cpt_table_engine_activation_results';

    /**
     * Transient expiration time (1 hour).
     */
    private const TRANSIENT_EXPIRATION = HOUR_IN_SECONDS;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_action('admin_notices', [$this, 'show_activation_notice']);
        add_action('admin_notices', [$this, 'show_schema_warnings']);
        add_action('admin_notices', [$this, 'show_license_limit_notice']);
    }

    /**
     * Store activation results for display.
     *
     * @param array<string, mixed> $results Activation results data.
     * @return bool True on success, false on failure.
     */
    public static function store_activation_results(array $results): bool
    {
        return set_transient(
            self::ACTIVATION_RESULTS_TRANSIENT,
            $results,
            self::TRANSIENT_EXPIRATION
        );
    }

    /**
     * Get activation results from transient.
     *
     * @return array<string, mixed>|false Activation results or false if not found.
     */
    private function get_activation_results()
    {
        return get_transient(self::ACTIVATION_RESULTS_TRANSIENT);
    }

    /**
     * Clear activation results transient.
     *
     * @return bool True if successful, false otherwise.
     */
    private function clear_activation_results(): bool
    {
        return delete_transient(self::ACTIVATION_RESULTS_TRANSIENT);
    }

    /**
     * Show activation notice.
     *
     * @return void
     */
    public function show_activation_notice(): void
    {
        $results = $this->get_activation_results();

        if (false === $results || !is_array($results)) {
            return;
        }

        // Only show on plugins or settings page.
        // Nonce verification is not required here because we are only reading the 'page' and 'action' parameters
        // to conditionally display an admin notice. This is a non-destructive read operation.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $screen  = get_current_screen();
        $page    = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $action  = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';

        if (!$screen || ($screen->id !== 'plugins' && $page !== 'slk-cpt-table-engine' && $action !== 'activate')) {
            return;
        }

        $existing_tables = $results['existing_tables'] ?? [];
        $tables_with_data = $results['tables_with_data'] ?? [];

        if (empty($existing_tables)) {
            // No existing tables found - show success.
            $this->render_notice(
                'success',
                __('CPT Table Engine activated successfully. No existing custom tables detected.', 'slk-cpt-table-engine')
            );
        } else {
            // Existing tables found.
            $table_count = count($existing_tables);
            $data_count  = count($tables_with_data);

            if ($data_count > 0) {
                $this->render_notice(
                    'warning',
                    sprintf(
                        /* translators: 1: Number of tables found, 2: Number of tables with data */
                        _n(
                            'CPT Table Engine detected %1$d existing custom table, %2$d containing data. The plugin will use these tables if compatible.',
                            'CPT Table Engine detected %1$d existing custom tables, %2$d containing data. The plugin will use these tables if compatible.',
                            $table_count,
                            'slk-cpt-table-engine'
                        ),
                        $table_count,
                        $data_count
                    ),
                    true
                );
            } else {
                $this->render_notice(
                    'info',
                    sprintf(
                        /* translators: %d: Number of tables found */
                        _n(
                            'CPT Table Engine detected %d existing empty custom table.',
                            'CPT Table Engine detected %d existing empty custom tables.',
                            $table_count,
                            'slk-cpt-table-engine'
                        ),
                        $table_count
                    ),
                    false
                );
            }
        }

        // Clear transient after showing.
        $this->clear_activation_results();
    }

    /**
     * Show schema warnings if any.
     *
     * @return void
     */
    public function show_schema_warnings(): void
    {
        $warnings = get_transient('cpt_table_engine_schema_warnings');

        if (false === $warnings || !is_array($warnings) || empty($warnings)) {
            return;
        }

        foreach ($warnings as $warning) {
            $this->render_notice(
                'warning',
                $warning,
                true
            );
        }

        // Clear after showing.
        delete_transient('cpt_table_engine_schema_warnings');
    }

    /**
     * Render an admin notice.
     *
     * @param string $type       Notice type: 'success', 'warning', 'error', 'info'.
     * @param string $message    The notice message.
     * @param bool   $dismissible Whether the notice is dismissible.
     * @return void
     */
    private function render_notice(string $type, string $message, bool $dismissible = true): void
    {
        $class = 'notice notice-' . $type;
        if ($dismissible) {
            $class .= ' is-dismissible';
        }

        printf(
            '<div class="%s"><p><strong>%s:</strong> %s</p></div>',
            esc_attr($class),
            esc_html__('CPT Table Engine', 'slk-cpt-table-engine'),
            wp_kses_post($message)
        );
    }

    /**
     * Store schema warning for display.
     *
     * @param string $table_name The table name.
     * @param string $message    The warning message.
     * @return void
     */
    public static function add_schema_warning(string $table_name, string $message): void
    {
        $warnings = get_transient('cpt_table_engine_schema_warnings');
        if (!is_array($warnings)) {
            $warnings = [];
        }

        $warnings[] = sprintf(
            /* translators: 1: Table name, 2: Warning message */
            __('Table %1$s: %2$s', 'slk-cpt-table-engine'),
            '<code>' . esc_html($table_name) . '</code>',
            $message
        );

        set_transient('cpt_table_engine_schema_warnings', $warnings, self::TRANSIENT_EXPIRATION);

        Logger::warning("Schema warning for {$table_name}: {$message}");
    }

    /**
     * Display notice about CPT activation limit for unlicensed users.
     *
     * Shows on settings page only when license is inactive.
     *
     * @return void
     */
    public function show_license_limit_notice(): void
    {
        // Only show on plugin settings page.
        $screen = get_current_screen();
        if (! $screen || $screen->id !== 'settings_page_slk-cpt-table-engine') {
            return;
        }

        // Only show if license is not active.
        if (LicenseChecker::is_active()) {
            return;
        }

        // Count enabled CPTs.
        $enabled_cpts = SettingsController::get_enabled_cpts();
        $count = count($enabled_cpts);

        // Show different messages based on usage.
        if ($count >= 3) {
            // At limit - warning (non-dismissible).
            $message = sprintf(
                /* translators: %s: URL to license page */
                __('You have reached the 3 CPT limit for the free version. <a href="%s">Activate your license</a> to enable unlimited custom post types.', 'slk-cpt-table-engine'),
                esc_url(admin_url('admin.php?page=slk-cpt-table-engine-license'))
            );
            $this->render_notice('warning', $message, false);
        }
    }

    /**
     * Render table handling mode explanation sidebar.
     *
     * Displays a helpful sidebar explaining what each table handling mode does.
     * This can be called from settings pages or admin areas.
     *
     * @return void
     */
    public static function render_mode_explanation_sidebar(): void
    {
?>
        <div class="cpt-table-engine-sidebar" style="background: #ffffff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin-top: 20px;">
            <h3 id="mode-sidebar-title" style="margin-top: 0; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">
                <span class="dashicons dashicons-info" style="color: #0073aa;"></span>
                <span class="mode-title-text"><?php esc_html_e('Selected Mode: Auto', 'slk-cpt-table-engine'); ?></span>
            </h3>

            <!-- Auto Mode -->
            <div class="mode-explanation" data-mode="auto" data-title="<?php esc_attr_e('Selected Mode: Auto', 'slk-cpt-table-engine'); ?>" style="display: none; margin-bottom: 20px; padding: 15px; background: #fff; border-left: 4px solid #46b450; border-radius: 3px; transition: opacity 0.3s ease-in-out;">
                <h4 style="margin: 0 0 10px 0; color: #46b450;">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e('Auto (Recommended)', 'slk-cpt-table-engine'); ?>
                </h4>
                <p style="margin: 0 0 10px 0;">
                    <strong><?php esc_html_e('What it does:', 'slk-cpt-table-engine'); ?></strong><br>
                    <?php esc_html_e('Uses WordPress dbDelta() to automatically create or update table schemas. If tables exist, it compares the schema and makes necessary updates while preserving data.', 'slk-cpt-table-engine'); ?>
                </p>
                <p style="margin: 0 0 10px 0;">
                    <strong><?php esc_html_e('Benefits:', 'slk-cpt-table-engine'); ?></strong><br>
                    • <?php esc_html_e('Non-destructive - preserves all existing data', 'slk-cpt-table-engine'); ?><br>
                    • <?php esc_html_e('Automatic schema updates on plugin upgrades', 'slk-cpt-table-engine'); ?><br>
                    • <?php esc_html_e('No manual intervention required', 'slk-cpt-table-engine'); ?>
                </p>
                <p style="margin: 0; padding: 8px; background: #f0f6f0; border-radius: 3px; font-size: 12px;">
                    <strong><?php esc_html_e('Best for:', 'slk-cpt-table-engine'); ?></strong>
                    <?php esc_html_e('Most users - this is the standard WordPress approach', 'slk-cpt-table-engine'); ?>
                </p>
            </div>

            <!-- Backup Mode -->
            <div class="mode-explanation" data-mode="backup" data-title="<?php esc_attr_e('Selected Mode: Backup', 'slk-cpt-table-engine'); ?>" style="display: none; margin-bottom: 20px; padding: 15px; background: #fff; border-left: 4px solid #00a0d2; border-radius: 3px; transition: opacity 0.3s ease-in-out;">
                <h4 style="margin: 0 0 10px 0; color: #00a0d2;">
                    <span class="dashicons dashicons-backup"></span>
                    <?php esc_html_e('Backup Before Modifications', 'slk-cpt-table-engine'); ?>
                </h4>
                <p style="margin: 0 0 10px 0;">
                    <strong><?php esc_html_e('What it does:', 'slk-cpt-table-engine'); ?></strong><br>
                    <?php esc_html_e('Creates timestamped backup copies of existing tables before any modifications. Backup tables are named with format: tablename_backup_YYYYMMDDHHMMSS', 'slk-cpt-table-engine'); ?>
                </p>
                <p style="margin: 0 0 10px 0;">
                    <strong><?php esc_html_e('Benefits:', 'slk-cpt-table-engine'); ?></strong><br>
                    • <?php esc_html_e('Extra safety layer for critical data', 'slk-cpt-table-engine'); ?><br>
                    • <?php esc_html_e('Easy rollback if issues occur', 'slk-cpt-table-engine'); ?><br>
                    • <?php esc_html_e('Peace of mind for production environments', 'slk-cpt-table-engine'); ?>
                </p>
                <p style="margin: 0 0 10px 0; padding: 8px; background: #fffbcc; border-radius: 3px; font-size: 12px;">
                    <strong><?php esc_html_e('Note:', 'slk-cpt-table-engine'); ?></strong>
                    <?php esc_html_e('Backup tables are not automatically deleted. You may need to clean them up manually to save disk space.', 'slk-cpt-table-engine'); ?>
                </p>
                <p style="margin: 0; padding: 8px; background: #e5f5fa; border-radius: 3px; font-size: 12px;">
                    <strong><?php esc_html_e('Best for:', 'slk-cpt-table-engine'); ?></strong>
                    <?php esc_html_e('Production sites with critical data or cautious administrators', 'slk-cpt-table-engine'); ?>
                </p>
            </div>

            <!-- Validate Mode -->
            <div class="mode-explanation" data-mode="validate" data-title="<?php esc_attr_e('Selected Mode: Validate', 'slk-cpt-table-engine'); ?>" style="display: none; margin-bottom: 20px; padding: 15px; background: #fff; border-left: 4px solid #ffb900; border-radius: 3px; transition: opacity 0.3s ease-in-out;">
                <h4 style="margin: 0 0 10px 0; color: #d68100;">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e('Validate Schema Only', 'slk-cpt-table-engine'); ?>
                </h4>
                <p style="margin: 0 0 10px 0;">
                    <strong><?php esc_html_e('What it does:', 'slk-cpt-table-engine'); ?></strong><br>
                    <?php esc_html_e('Checks existing table structures against expected schemas and displays warnings if mismatches are found. Does NOT modify any tables.', 'slk-cpt-table-engine'); ?>
                </p>
                <p style="margin: 0 0 10px 0;">
                    <strong><?php esc_html_e('Benefits:', 'slk-cpt-table-engine'); ?></strong><br>
                    • <?php esc_html_e('Safe inspection without changes', 'slk-cpt-table-engine'); ?><br>
                    • <?php esc_html_e('Identifies schema conflicts before they cause issues', 'slk-cpt-table-engine'); ?><br>
                    • <?php esc_html_e('Useful for troubleshooting and debugging', 'slk-cpt-table-engine'); ?>
                </p>
                <p style="margin: 0; padding: 8px; background: #fff8e5; border-radius: 3px; font-size: 12px;">
                    <strong><?php esc_html_e('Best for:', 'slk-cpt-table-engine'); ?></strong>
                    <?php esc_html_e('Development/staging environments, debugging schema issues', 'slk-cpt-table-engine'); ?>
                </p>
            </div>

            <!-- Skip Mode -->
            <div class="mode-explanation" data-mode="skip" data-title="<?php esc_attr_e('Selected Mode: Skip', 'slk-cpt-table-engine'); ?>" style="display: none; margin-bottom: 0; padding: 15px; background: #fff; border-left: 4px solid #dc3232; border-radius: 3px; transition: opacity 0.3s ease-in-out;">
                <h4 style="margin: 0 0 10px 0; color: #dc3232;">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php esc_html_e('Skip Existing Tables', 'slk-cpt-table-engine'); ?>
                </h4>
                <p style="margin: 0 0 10px 0;">
                    <strong><?php esc_html_e('What it does:', 'slk-cpt-table-engine'); ?></strong><br>
                    <?php esc_html_e('Leaves all existing tables completely untouched. The plugin will use them as-is without any validation or modifications.', 'slk-cpt-table-engine'); ?>
                </p>
                <p style="margin: 0 0 10px 0;">
                    <strong><?php esc_html_e('Benefits:', 'slk-cpt-table-engine'); ?></strong><br>
                    • <?php esc_html_e('Maximum control - no automatic changes', 'slk-cpt-table-engine'); ?><br>
                    • <?php esc_html_e('Useful for custom table structures', 'slk-cpt-table-engine'); ?>
                </p>
                <p style="margin: 0 0 10px 0; padding: 8px; background: #fef0f0; border: 1px solid #dc3232; border-radius: 3px; font-size: 12px;">
                    <strong style="color: #dc3232;">⚠️ <?php esc_html_e('Warning:', 'slk-cpt-table-engine'); ?></strong><br>
                    <?php esc_html_e('If table schemas are incompatible, you may experience errors or data corruption. Use only if you know what you are doing!', 'slk-cpt-table-engine'); ?>
                </p>
                <p style="margin: 0; padding: 8px; background: #fcf0f1; border-radius: 3px; font-size: 12px;">
                    <strong><?php esc_html_e('Best for:', 'slk-cpt-table-engine'); ?></strong>
                    <?php esc_html_e('Advanced users with custom-modified tables or specific requirements', 'slk-cpt-table-engine'); ?>
                </p>
            </div>

            <!-- Quick Tips -->
            <div class="mode-tips" style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ccc; border-radius: 3px;">
                <h4 style="margin: 0 0 10px 0; color: #555;">
                    <span class="dashicons dashicons-lightbulb"></span>
                    <?php esc_html_e('Quick Tips', 'slk-cpt-table-engine'); ?>
                </h4>
                <ul style="margin: 0; padding-left: 20px; line-height: 1.6; font-size: 13px;">
                    <li><?php esc_html_e('The mode setting applies to both plugin activation and when enabling individual CPTs', 'slk-cpt-table-engine'); ?></li>
                    <li><?php esc_html_e('You can change the mode at any time in Settings → Advanced', 'slk-cpt-table-engine'); ?></li>
                    <li><?php esc_html_e('All modes are logged - check your debug.log for detailed information', 'slk-cpt-table-engine'); ?></li>
                    <li><?php esc_html_e('When in doubt, stick with "Auto" - it is tested and reliable', 'slk-cpt-table-engine'); ?></li>
                </ul>
            </div>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Function to show the selected mode explanation
                function showSelectedMode() {
                    var selectedMode = $('#table_handling_mode').val();

                    // Hide all mode explanations
                    $('.mode-explanation').hide();

                    // Get the selected mode box and its title
                    var $selectedBox = $('.mode-explanation[data-mode="' + selectedMode + '"]');
                    var modeTitle = $selectedBox.attr('data-title');

                    // Update the title
                    $('.mode-title-text').text(modeTitle);

                    // Show only the selected mode
                    $selectedBox.fadeIn(300);
                }

                // Show the correct box on page load
                showSelectedMode();

                // Update when selection changes
                $('#table_handling_mode').on('change', function() {
                    showSelectedMode();
                });
            });
        </script>
<?php
    }
}
