<?php

/**
 * Settings Page for CPT Table Engine.
 *
 * Handles admin settings page UI and rendering.
 *
 * @package SLK_Cpt_Table_Engine
 */

declare(strict_types=1);

namespace SLK\Cpt_Table_Engine\Admin;


use SLK\Cpt_Table_Engine\Controllers\Settings_Controller;

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
        $cpt_tab_url     = wp_nonce_url(admin_url('options-general.php?page=' . self::PAGE_SLUG . '&tab=cpt'), 'cpt_table_engine_tab_action');
        $license_tab_url = wp_nonce_url(admin_url('options-general.php?page=' . self::PAGE_SLUG . '&tab=license'), 'cpt_table_engine_tab_action');

?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <!-- Tab Navigation -->
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url($cpt_tab_url); ?>" class="nav-tab <?php echo $current_tab === 'cpt' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Custom Post Types', 'slk-cpt-table-engine'); ?>
                </a>
                <a href="<?php echo esc_url($license_tab_url); ?>" class="nav-tab <?php echo $current_tab === 'license' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('License', 'slk-cpt-table-engine'); ?>
                </a>
            </h2>

            <!-- Tab Content -->
            <div class="tab-content">
                <?php if ($current_tab === 'cpt') : ?>
                    <!-- Custom Post Types Tab -->
                    <p class="description">
                        <?php esc_html_e('Enable custom table storage for specific Custom Post Types to optimize database performance.', 'slk-cpt-table-engine'); ?>
                    </p>

                    <?php if (empty($settings)) : ?>
                        <div class="notice notice-info">
                            <p><?php esc_html_e('No custom post types found. Custom post types will appear here once registered.', 'slk-cpt-table-engine'); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="cpt-table-engine-info" style="margin-top: 30px;">
                        <h2><?php esc_html_e('How It Works', 'slk-cpt-table-engine'); ?></h2>
                        <ul>
                            <li><?php esc_html_e('When enabled, posts of the selected type are stored in dedicated custom tables instead of wp_posts.', 'slk-cpt-table-engine'); ?></li>
                            <li><?php esc_html_e('This can significantly improve query performance for post types with large datasets.', 'slk-cpt-table-engine'); ?></li>
                            <li><?php esc_html_e('All existing posts are automatically migrated when you enable custom table storage.', 'slk-cpt-table-engine'); ?></li>
                            <li><?php esc_html_e('You can safely switch back to wp_posts at any time - all data will be migrated back.', 'slk-cpt-table-engine'); ?></li>
                            <li><?php esc_html_e('WP_Query and all standard WordPress functions continue to work normally.', 'slk-cpt-table-engine'); ?></li>
                        </ul>
                    </div>
                    <br />

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

                <?php elseif ($current_tab === 'license') : ?>
                    <!-- License Tab -->
                    <div id="cpt-license-container" style="margin-top: 20px;">
                        <?php
                        // Render the license form
                        \SLK\License_Manager\License_Manager::instance()->render_license_form();
                        ?>
                    </div>

                <?php endif; ?>
            </div>
        </div>
<?php
    }
}
