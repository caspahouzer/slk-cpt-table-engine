<?php

/**
 * Uninstall handler for CPT Table Engine.
 *
 * Fired when the plugin is uninstalled.
 *
 * @package CPT_Table_Engine
 */

declare(strict_types=1);

// Exit if accessed directly or not in uninstall context.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

/**
 * Drop all custom CPT tables.
 *
 * @return void
 */
function cpt_table_engine_drop_all_tables(): void
{
    global $wpdb;

    // Get all tables matching our pattern.
    $pattern = $wpdb->esc_like($wpdb->prefix . 'cpt_') . '%';
    $tables = $wpdb->get_col(
        $wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $pattern
        )
    );

    // Drop each table.
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
}

/**
 * Delete all plugin options.
 *
 * @return void
 */
function cpt_table_engine_delete_options(): void
{
    delete_option('cpt_table_engine_settings');
    delete_option('cpt_table_engine_version');
    delete_option('cpt_table_engine_enabled_cpts');
}

/**
 * Clean up transients.
 *
 * @return void
 */
function cpt_table_engine_clean_transients(): void
{
    global $wpdb;

    // Delete all transients related to this plugin.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_cpt_table_engine_') . '%',
            $wpdb->esc_like('_transient_timeout_cpt_table_engine_') . '%'
        )
    );
}

/**
 * Clean up object cache.
 *
 * @return void
 */
function cpt_table_engine_clean_cache(): void
{
    wp_cache_flush();
}

// Execute cleanup.
cpt_table_engine_drop_all_tables();
cpt_table_engine_delete_options();
cpt_table_engine_clean_transients();
cpt_table_engine_clean_cache();
