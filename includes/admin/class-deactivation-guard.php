<?php

/**
 * Deactivation Guard for CPT Table Engine.
 *
 * Prevents plugin deactivation when CPTs are using custom tables.
 *
 * @package CPT_Table_Engine
 */

declare(strict_types=1);

namespace CPT_Table_Engine\Admin;

use CPT_Table_Engine\Controllers\Settings_Controller;

/**
 * Deactivation Guard class.
 */
final class Deactivation_Guard
{
    /**
     * Transient key for deactivation notice.
     */
    private const NOTICE_TRANSIENT = 'cpt_table_engine_deactivation_prevented';

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
        add_action('admin_notices', [$this, 'show_deactivation_notice']);
    }

    /**
     * Check if deactivation should be allowed.
     *
     * @return bool True if deactivation is allowed, false otherwise.
     */
    public static function can_deactivate(): bool
    {
        $enabled_cpts = Settings_Controller::get_enabled_cpts();
        return empty($enabled_cpts);
    }

    /**
     * Prevent deactivation if CPTs are enabled.
     *
     * @return void
     */
    public static function prevent_deactivation(): void
    {
        if (! self::can_deactivate()) {
            // Set transient for admin notice.
            set_transient(self::NOTICE_TRANSIENT, true, 30);

            // Reactivate the plugin.
            activate_plugin(CPT_TABLE_ENGINE_BASENAME);
        }
    }

    /**
     * Show admin notice when deactivation is prevented.
     *
     * @return void
     */
    public function show_deactivation_notice(): void
    {
        if (! get_transient(self::NOTICE_TRANSIENT)) {
            return;
        }

        // Delete transient.
        delete_transient(self::NOTICE_TRANSIENT);

        $settings_url = admin_url('options-general.php?page=cpt-table-engine');
?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php esc_html_e('CPT Table Engine cannot be deactivated!', 'cpt-table-engine'); ?></strong>
            </p>
            <p>
                <?php
                printf(
                    /* translators: %s: URL to settings page */
                    esc_html__('You must migrate all Custom Post Types back to wp_posts before deactivating this plugin. Please go to the %s and disable all CPTs first.', 'cpt-table-engine'),
                    '<a href="' . esc_url($settings_url) . '">' . esc_html__('settings page', 'cpt-table-engine') . '</a>'
                );
                ?>
            </p>
        </div>
<?php
    }
}
