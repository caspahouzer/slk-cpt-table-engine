<?php

declare(strict_types=1);

namespace SLK\Cpt_Table_Engine\Admin;

use SLK\Cpt_Table_Engine\Controllers\Settings_Controller;
use SLK\Cpt_Table_Engine\Database\Table_Manager;

/**
 * Settings Page class.
 */
final class Settings_Page
{
    /**
     * Page slug.
     */
    private const PAGE_SLUG = 'slk-cpt-table-engine';

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
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('plugin_action_links_' . CPT_TABLE_ENGINE_BASENAME, [$this, 'add_settings_link']);
    }

    /**
     * Add settings page to WordPress admin menu.
     *
     * @return void
     */
    public function add_settings_page(): void
    {
        add_options_page(
            'CPT Table Engine',
            'CPT Table Engine',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Add settings link to plugin action links.
     *
     * @param array $links Existing plugin action links.
     * @return array Modified plugin action links.
     */
    public function add_settings_link(array $links): array
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=' . self::PAGE_SLUG)),
            esc_html__('Settings', 'slk-cpt-table-engine')
        );

        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Register plugin settings.
     *
     * @return void
     */
    public function register_settings(): void
    {
        register_setting(
            'cpt_table_engine_advanced',
            'cpt_table_engine_table_handling_mode',
            [
                'type'              => 'string',
                'default'           => 'auto',
                'sanitize_callback' => [$this, 'sanitize_table_handling_mode'],
            ]
        );
    }

    /**
     * Sanitize table handling mode.
     *
     * @param string $value The value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize_table_handling_mode(string $value): string
    {
        $valid_modes = ['auto', 'backup', 'validate', 'skip'];

        if (!in_array($value, $valid_modes, true)) {
            return 'auto';
        }

        return $value;
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_assets(string $hook): void
    {
        // Only load on our settings page.
        if ('settings_page_' . self::PAGE_SLUG !== $hook) {
            return;
        }

        // Enqueue CSS.
        wp_enqueue_style(
            'cpt-table-engine-admin',
            CPT_TABLE_ENGINE_URL . 'assets/css/admin.css',
            [],
            CPT_TABLE_ENGINE_VERSION
        );

        // Enqueue JavaScript.
        wp_enqueue_script(
            'cpt-table-engine-admin',
            CPT_TABLE_ENGINE_URL . 'assets/js/admin.js',
            ['jquery'],
            CPT_TABLE_ENGINE_VERSION,
            true
        );

        // Localize script.
        wp_localize_script(
            'cpt-table-engine-admin',
            'cptTableEngine',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('cpt_table_engine_nonce'),
                'i18n'    => [
                    'confirmDisable' => __('Are you sure you want to disable custom table storage? This will migrate all data back to wp_posts.', 'slk-cpt-table-engine'),
                    'migrating'      => __('Migrating...', 'slk-cpt-table-engine'),
                    'success'        => __('Migration completed successfully!', 'slk-cpt-table-engine'),
                    'error'          => __('Migration failed. Please check the error log.', 'slk-cpt-table-engine'),
                    'usingCustomTable' => __('Using custom table', 'slk-cpt-table-engine'),
                    'usingWpPosts'   => __('Using wp_posts', 'slk-cpt-table-engine'),
                    'funnyMessages'  => [
                        __('Reticulating splines...', 'slk-cpt-table-engine'),
                        __('Gerbil feeding time...', 'slk-cpt-table-engine'),
                        __('Recalibrating flux capacitor...', 'slk-cpt-table-engine'),
                        __('Bending the space-time continuum...', 'slk-cpt-table-engine'),
                        __('Definitely not downloading a car...', 'slk-cpt-table-engine'),
                        __('Dividing by zero...', 'slk-cpt-table-engine'),
                        __('Twiddling thumbs...', 'slk-cpt-table-engine'),
                        __('Warming up the hamsters...', 'slk-cpt-table-engine'),
                        __('It\'s not you, it\'s me...', 'slk-cpt-table-engine'),
                        __('Constructing additional pylons...', 'slk-cpt-table-engine'),
                        __('Compiling the internet...', 'slk-cpt-table-engine'),
                        __('Debugging the coffee machine...', 'slk-cpt-table-engine'),
                        __('Converting caffeine to code...', 'slk-cpt-table-engine'),
                        __('Optimizing SQL queries nobody will read...', 'slk-cpt-table-engine'),
                        __('Teaching robots to love...', 'slk-cpt-table-engine'),
                        __('Untangling Ethernet cables...', 'slk-cpt-table-engine'),
                        __('Reversing the polarity...', 'slk-cpt-table-engine'),
                        __('Summoning the database gremlins...', 'slk-cpt-table-engine'),
                        __('Applying percussive maintenance...', 'slk-cpt-table-engine'),
                        __('Installing Adobe Reader...', 'slk-cpt-table-engine'),
                    ],
                ],
            ]
        );
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_settings_page(): void
    {
        // Check user capabilities.
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'slk-cpt-table-engine'));
        }

        // Get current tab.
        $current_tab = 'cpt';
        if (isset($_GET['tab'])) {
            check_admin_referer('cpt_table_engine_tab_action');
            $current_tab = sanitize_text_field(wp_unslash($_GET['tab']));
        }

        // Get settings.
        $settings = Settings_Controller::get_settings_for_display();

        // Generate tab URLs with nonces.
        $cpt_tab_url      = wp_nonce_url(admin_url('options-general.php?page=' . self::PAGE_SLUG . '&tab=cpt'), 'cpt_table_engine_tab_action');
        $license_tab_url  = wp_nonce_url(admin_url('options-general.php?page=' . self::PAGE_SLUG . '&tab=license'), 'cpt_table_engine_tab_action');
        $advanced_tab_url = wp_nonce_url(admin_url('options-general.php?page=' . self::PAGE_SLUG . '&tab=advanced'), 'cpt_table_engine_tab_action');

        // Get table handling mode.
        $handling_mode = get_option('cpt_table_engine_table_handling_mode', 'auto');

?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <!-- Tab Navigation -->
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url($cpt_tab_url); ?>" class="nav-tab <?php echo $current_tab === 'cpt' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Custom Post Types', 'slk-cpt-table-engine'); ?>
                </a>
                <a href="<?php echo esc_url($advanced_tab_url); ?>" class="nav-tab <?php echo $current_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Advanced', 'slk-cpt-table-engine'); ?>
                </a>
                <a href="<?php echo esc_url($license_tab_url); ?>" class="nav-tab <?php echo $current_tab === 'license' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('License', 'slk-cpt-table-engine'); ?>
                </a>
            </h2>

            <!-- Tab Content -->
            <div class="tab-content">
                <?php if ($current_tab === 'cpt') : ?>

                    <!-- Advanced Tab with Sidebar Layout -->
                    <div style="display: flex; gap: 20px; margin-top: 20px;">

                        <!-- Main Content Column (Left) -->
                        <div style="flex: 0 0 65%; max-width: 65%;">

                            <!-- Custom Post Types Tab -->
                            <p class="description">
                                <?php esc_html_e('Enable custom table storage for specific Custom Post Types to optimize database performance.', 'slk-cpt-table-engine'); ?>
                            </p>

                            <?php if (!empty($settings)) : ?>
                                <?php
                                $has_enabled = false;
                                foreach ($settings as $setting) {
                                    if ($setting['enabled']) {
                                        $has_enabled = true;
                                        break;
                                    }
                                }
                                if ($has_enabled) :
                                ?>
                                    <div class="inline notice notice-warning">
                                        <p>
                                            <strong><?php esc_html_e('Important:', 'slk-cpt-table-engine'); ?></strong>
                                            <?php esc_html_e('Before deactivating this plugin, you must disable all CPTs. This ensures all data is safely migrated back to wp_posts.', 'slk-cpt-table-engine'); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (empty($settings)) : ?>
                                <div class="inline notice notice-info">
                                    <p><?php esc_html_e('No custom post types found. Custom post types will appear here once registered.', 'slk-cpt-table-engine'); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($settings)) : ?>
                                <table class="fixed wp-list-table widefat striped">
                                    <thead>
                                        <tr>
                                            <th scope="col"><?php esc_html_e('Post Type', 'slk-cpt-table-engine'); ?></th>
                                            <th scope="col"><?php esc_html_e('Label', 'slk-cpt-table-engine'); ?></th>
                                            <th scope="col"><?php esc_html_e('Posts', 'slk-cpt-table-engine'); ?></th>
                                            <th scope="col"><?php esc_html_e('Custom Table Storage', 'slk-cpt-table-engine'); ?></th>
                                            <th scope="col"><?php esc_html_e('Status', 'slk-cpt-table-engine'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($settings as $setting) : ?>
                                            <tr data-post-type="<?php echo esc_attr($setting['slug']); ?>">
                                                <td><code><?php echo esc_html($setting['slug']); ?></code></td>
                                                <td><?php echo esc_html($setting['label']); ?></td>
                                                <td><?php echo esc_html(number_format_i18n($setting['count'])); ?></td>
                                                <td>
                                                    <label class="cpt-toggle-switch">
                                                        <input
                                                            type="checkbox"
                                                            class="cpt-toggle-checkbox"
                                                            data-post-type="<?php echo esc_attr($setting['slug']); ?>"
                                                            <?php checked($setting['enabled']); ?>>
                                                        <span class="cpt-toggle-slider"></span>
                                                    </label>
                                                </td>
                                                <td>
                                                    <span class="cpt-status">
                                                        <?php if ($setting['enabled']) : ?>
                                                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                                            <?php esc_html_e('Using custom table', 'slk-cpt-table-engine'); ?>
                                                        <?php else : ?>
                                                            <span class="dashicons dashicons-minus" style="color: #999;"></span>
                                                            <?php esc_html_e('Using wp_posts', 'slk-cpt-table-engine'); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                    <div class="cpt-progress" style="display: none;">
                                                        <span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>
                                                        <span class="cpt-progress-text"></span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>

                        </div>

                        <!-- Sidebar Column (Right) -->
                        <div style="flex: 0 0 34%; max-width: 34%;">

                            <div class="cpt-table-engine-sidebar" style="background: #ffffff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin-top: 20px;">
                                <h3 id="mode-sidebar-title" style="margin-top: 0; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">
                                    <span class="dashicons dashicons-info" style="color: #0073aa;"></span>
                                    <span class="mode-title-text"><?php esc_html_e('How It Works', 'slk-cpt-table-engine'); ?></span>
                                </h3>
                                <ul>
                                    <li><?php esc_html_e('When enabled, posts of the selected type are stored in dedicated custom tables instead of wp_posts.', 'slk-cpt-table-engine'); ?></li>
                                    <li><?php esc_html_e('This can significantly improve query performance for post types with large datasets.', 'slk-cpt-table-engine'); ?></li>
                                    <li><?php esc_html_e('All existing posts are automatically migrated when you enable custom table storage.', 'slk-cpt-table-engine'); ?></li>
                                    <li><?php esc_html_e('You can safely switch back to wp_posts at any time - all data will be migrated back.', 'slk-cpt-table-engine'); ?></li>
                                    <li><?php esc_html_e('WP_Query and all standard WordPress functions continue to work normally.', 'slk-cpt-table-engine'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                <?php elseif ($current_tab === 'license') : ?>
                    <!-- License Tab -->
                    <div id="cpt-license-container" style="margin-top: 20px;">
                        <?php
                        // Render the license form
                        \SLK\License_Manager\License_Manager::instance()->render_license_form();
                        ?>
                    </div>

                <?php elseif ($current_tab === 'advanced') : ?>
                    <!-- Advanced Tab with Sidebar Layout -->
                    <div style="display: flex; gap: 20px; margin-top: 20px;">

                        <!-- Main Content Column (Left) -->
                        <div style="flex: 0 0 65%; max-width: 65%;">
                            <h2><?php esc_html_e('Table Handling Settings', 'slk-cpt-table-engine'); ?></h2>
                            <p class="description">
                                <?php esc_html_e('Configure how the plugin handles existing CPT tables during activation and when enabling custom storage.', 'slk-cpt-table-engine'); ?>
                            </p>

                            <form method="post" action="options.php">
                                <?php settings_fields('cpt_table_engine_advanced'); ?>

                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="table_handling_mode"><?php esc_html_e('Table Handling Mode', 'slk-cpt-table-engine'); ?></label>
                                        </th>
                                        <td>
                                            <select name="cpt_table_engine_table_handling_mode" id="table_handling_mode" style="min-width: 250px;">
                                                <option value="auto" <?php selected($handling_mode, 'auto'); ?>>
                                                    <?php esc_html_e('Auto (Recommended)', 'slk-cpt-table-engine'); ?>
                                                </option>
                                                <option value="backup" <?php selected($handling_mode, 'backup'); ?>>
                                                    <?php esc_html_e('Backup Before Modifications', 'slk-cpt-table-engine'); ?>
                                                </option>
                                                <option value="validate" <?php selected($handling_mode, 'validate'); ?>>
                                                    <?php esc_html_e('Validate Schema Only', 'slk-cpt-table-engine'); ?>
                                                </option>
                                                <option value="skip" <?php selected($handling_mode, 'skip'); ?>>
                                                    <?php esc_html_e('Skip Existing Tables', 'slk-cpt-table-engine'); ?>
                                                </option>
                                            </select>
                                            <p class="description" style="margin-top: 8px;">
                                                <?php esc_html_e('Select how the plugin should handle existing custom post type tables. See the sidebar for detailed explanations.', 'slk-cpt-table-engine'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>

                                <?php submit_button(); ?>
                            </form>

                            <hr style="margin: 30px 0;">

                            <h2><?php esc_html_e('Existing CPT Tables', 'slk-cpt-table-engine'); ?></h2>
                            <?php
                            $existing_tables = Table_Manager::detect_existing_tables();
                            if (empty($existing_tables)) :
                            ?>
                                <p><?php esc_html_e('No custom post type tables found.', 'slk-cpt-table-engine'); ?></p>
                            <?php else : ?>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th style="width: 40%;"><?php esc_html_e('Table Name', 'slk-cpt-table-engine'); ?></th>
                                            <th style="width: 15%;"><?php esc_html_e('Type', 'slk-cpt-table-engine'); ?></th>
                                            <th style="width: 15%;"><?php esc_html_e('Rows', 'slk-cpt-table-engine'); ?></th>
                                            <th style="width: 30%;"><?php esc_html_e('Status', 'slk-cpt-table-engine'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($existing_tables as $table_info) : ?>
                                            <tr>
                                                <td><code><?php echo esc_html($table_info['name']); ?></code></td>
                                                <td><?php echo esc_html(ucfirst($table_info['type'])); ?></td>
                                                <td><?php echo esc_html(number_format_i18n($table_info['row_count'])); ?></td>
                                                <td>
                                                    <?php if ($table_info['has_data']) : ?>
                                                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                                        <?php esc_html_e('Contains data', 'slk-cpt-table-engine'); ?>
                                                    <?php else : ?>
                                                        <span class="dashicons dashicons-minus" style="color: #999;"></span>
                                                        <?php esc_html_e('Empty', 'slk-cpt-table-engine'); ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <!-- Sidebar Column (Right) -->
                        <div style="flex: 0 0 34%; max-width: 34%;">
                            <?php Table_Admin_Notices::render_mode_explanation_sidebar(); ?>
                        </div>

                    </div>

                <?php endif; ?>
            </div>
        </div>
<?php
    }
}
