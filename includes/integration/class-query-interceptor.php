<?php

/**
 * Query Interceptor for CPT Table Engine.
 *
 * Intercepts WP_Query and redirects to custom tables when appropriate.
 *
 * @package CPT_Table_Engine
 */

declare(strict_types=1);

namespace CPT_Table_Engine\Integration;

use CPT_Table_Engine\Controllers\Settings_Controller;
use CPT_Table_Engine\Database\Table_Manager;
use CPT_Table_Engine\Helpers\Logger;

/**
 * Query Interceptor class.
 */
final class Query_Interceptor
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
        add_filter('posts_request', [$this, 'filter_posts_request'], 10, 2);
        add_filter('the_posts', [$this, 'filter_the_posts'], 10, 2);
    }

    /**
     * Filter the posts SQL query to use custom tables.
     *
     * @param string    $request The SQL query.
     * @param \WP_Query $query   The WP_Query instance.
     * @return string Modified SQL query.
     */
    public function filter_posts_request(string $request, \WP_Query $query): string
    {
        // Get the post type from query.
        $post_type = $query->get('post_type');

        // Skip if no post type or multiple post types.
        if (empty($post_type) || is_array($post_type)) {
            return $request;
        }

        // Skip if post type doesn't use custom tables.
        if (! Settings_Controller::is_enabled($post_type)) {
            return $request;
        }

        // Skip if tables don't exist.
        if (! Table_Manager::verify_tables($post_type)) {
            return $request;
        }

        global $wpdb;

        // Get custom table names.
        $custom_table = Table_Manager::get_table_name($post_type, 'main');
        $custom_meta_table = Table_Manager::get_table_name($post_type, 'meta');

        // Replace wp_posts with custom table.
        $request = str_replace($wpdb->posts, $custom_table, $request);

        // Replace wp_postmeta with custom meta table.
        $request = str_replace($wpdb->postmeta, $custom_meta_table, $request);

        Logger::debug("Intercepted query for post type: {$post_type}");

        return $request;
    }

    /**
     * Filter the posts results to ensure proper object structure.
     *
     * @param array     $posts The array of post objects.
     * @param \WP_Query $query The WP_Query instance.
     * @return array Modified posts array.
     */
    public function filter_the_posts(array $posts, \WP_Query $query): array
    {
        // Get the post type from query.
        $post_type = $query->get('post_type');

        // Skip if no post type or multiple post types.
        if (empty($post_type) || is_array($post_type)) {
            return $posts;
        }

        // Skip if post type doesn't use custom tables.
        if (! Settings_Controller::is_enabled($post_type)) {
            return $posts;
        }

        // Ensure all posts are proper WP_Post objects.
        foreach ($posts as $key => $post) {
            if (! $post instanceof \WP_Post) {
                $posts[$key] = new \WP_Post($post);
            }
        }

        return $posts;
    }
}
