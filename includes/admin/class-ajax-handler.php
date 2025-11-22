<?php

/**
 * AJAX Handler for CPT Table Engine.
 *
 * Handles AJAX requests from the admin interface.
 *
 * @package CPT_Table_Engine
 */

declare(strict_types=1);

namespace CPT_Table_Engine\Admin;

use CPT_Table_Engine\Migrations\Migration_Manager;
use CPT_Table_Engine\Helpers\Sanitizer;
use CPT_Table_Engine\Helpers\Validator;

/**
 * AJAX Handler class.
 */
final class Ajax_Handler
{
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
        add_action('wp_ajax_cpt_table_engine_toggle_cpt', [$this, 'handle_toggle_cpt']);
        add_action('wp_ajax_cpt_table_engine_migration_status', [$this, 'handle_migration_status']);
    }

    /**
     * Handle toggle CPT AJAX request.
     *
     * @return void
     */
    public function handle_toggle_cpt(): void
    {
        // Verify nonce.
        check_ajax_referer('cpt_table_engine_nonce', 'nonce');

        // Check user capabilities.
        if (! current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action.', 'cpt-table-engine'),
            ]);
        }

        // Get and sanitize post type.
        $post_type = isset($_POST['post_type']) ? Sanitizer::sanitize_post_type(sanitize_text_field(wp_unslash($_POST['post_type']))) : '';
        $enabled = isset($_POST['enabled']) && 'true' === $_POST['enabled'];

        // Validate post type.
        if (! Validator::is_custom_post_type($post_type)) {
            wp_send_json_error([
                'message' => __('Invalid post type.', 'cpt-table-engine'),
            ]);
        }

        // Perform migration.
        if ($enabled) {
            // Enable custom table.
            $result = Migration_Manager::migrate_to_custom_table($post_type);
        } else {
            // Disable custom table.
            $result = Migration_Manager::migrate_to_wp_posts($post_type);
        }

        // Check result.
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        }

        wp_send_json_success([
            'message' => $enabled
                ? __('Custom table enabled successfully.', 'cpt-table-engine')
                : __('Custom table disabled successfully.', 'cpt-table-engine'),
        ]);
    }

    /**
     * Handle migration status AJAX request.
     *
     * @return void
     */
    public function handle_migration_status(): void
    {
        // Verify nonce.
        check_ajax_referer('cpt_table_engine_nonce', 'nonce');

        // Check user capabilities.
        if (! current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action.', 'cpt-table-engine'),
            ]);
        }

        // Get and sanitize post type.
        $post_type = isset($_POST['post_type']) ? Sanitizer::sanitize_post_type(sanitize_text_field(wp_unslash($_POST['post_type']))) : '';

        // Validate post type.
        if (empty($post_type)) {
            wp_send_json_error([
                'message' => __('Invalid post type.', 'cpt-table-engine'),
            ]);
        }

        // Get migration status.
        $status = Migration_Manager::get_migration_status($post_type);

        wp_send_json_success($status);
    }
}
