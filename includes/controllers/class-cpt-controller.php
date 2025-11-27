<?php

/**
 * CPT Controller for CPT Table Engine.
 *
 * Handles CRUD operations for CPT entries in custom tables.
 *
 * @package SLK_Cpt_Table_Engine
 */

declare(strict_types=1);

namespace SLK\Cpt_Table_Engine\Controllers;

use SLK\Cpt_Table_Engine\Database\Table_Manager;
use SLK\Cpt_Table_Engine\Helpers\Sanitizer;
use SLK\Cpt_Table_Engine\Helpers\Validator;
use SLK\Cpt_Table_Engine\Helpers\Cache;
use SLK\Cpt_Table_Engine\Helpers\Logger;

/**
 * CPT Controller class.
 */
final class CPT_Controller
{
    /**
     * Insert a new CPT entry.
     *
     * @param string $post_type The post type.
     * @param array  $data      The post data.
     * @return int|\WP_Error The inserted post ID or WP_Error on failure.
     */
    public static function insert(string $post_type, array $data)
    {
        global $wpdb;

        // Validate post data.
        $validation = Validator::validate_post_data(array_merge($data, ['post_type' => $post_type]));
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Sanitize post data.
        $data = Sanitizer::sanitize_post_data($data);

        // Set defaults.
        $defaults = [
            'post_content'        => '',
            'post_excerpt'        => '',
            'post_status'         => 'draft',
            'post_date'           => current_time('mysql'),
            'post_date_gmt'       => current_time('mysql', true),
            'post_modified'       => current_time('mysql'),
            'post_modified_gmt'   => current_time('mysql', true),
            'post_author'         => get_current_user_id(),
            'post_name'           => '',
            'post_type'           => $post_type,
            'post_parent'         => 0,
            'menu_order'          => 0,
            'comment_status'      => 'closed',
            'ping_status'         => 'closed',
            'comment_count'       => 0,
            'guid'                => '',
        ];

        $data = wp_parse_args($data, $defaults);

        // Generate post_name if empty.
        if (empty($data['post_name']) && ! empty($data['post_title'])) {
            $data['post_name'] = sanitize_title($data['post_title']);
        }

        // Get table name.
        $table_name = Table_Manager::get_table_name($post_type, 'main');

        // Insert into database.
        $result = $wpdb->insert(
            $table_name,
            $data,
            [
                '%s', // post_title
                '%s', // post_content
                '%s', // post_excerpt
                '%s', // post_status
                '%s', // post_date
                '%s', // post_date_gmt
                '%s', // post_modified
                '%s', // post_modified_gmt
                '%d', // post_author
                '%s', // post_name
                '%s', // post_type
                '%d', // post_parent
                '%d', // menu_order
                '%s', // comment_status
                '%s', // ping_status
                '%d', // comment_count
                '%s', // guid
            ]
        );

        if (false === $result) {
            Logger::error("Failed to insert post into {$table_name}: " . $wpdb->last_error);
            return new \WP_Error('db_insert_error', __('Could not insert post into database.', 'slk-cpt-table-engine'));
        }

        $post_id = (int) $wpdb->insert_id;

        // Update GUID if empty.
        if (empty($data['guid'])) {
            $guid = get_permalink($post_id);
            if (! $guid) {
                $guid = home_url("?p={$post_id}");
            }

            $wpdb->update(
                $table_name,
                ['guid' => $guid],
                ['ID' => $post_id],
                ['%s'],
                ['%d']
            );
        }

        // Clear cache.
        Cache::delete_post($post_id, $post_type);
        Cache::flush_post_type($post_type);

        Logger::info("Inserted post ID {$post_id} into {$table_name}");

        return $post_id;
    }

    /**
     * Update an existing CPT entry.
     *
     * @param string $post_type The post type.
     * @param int    $post_id   The post ID.
     * @param array  $data      The post data to update.
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public static function update(string $post_type, int $post_id, array $data)
    {
        global $wpdb;

        // Validate post ID.
        if (! Validator::is_valid_post_id($post_id)) {
            return new \WP_Error('invalid_post_id', __('Invalid post ID.', 'slk-cpt-table-engine'));
        }

        // Sanitize post data.
        $data = Sanitizer::sanitize_post_data($data);

        // Update post_modified timestamps.
        $data['post_modified']     = current_time('mysql');
        $data['post_modified_gmt'] = current_time('mysql', true);

        // Get table name.
        $table_name = Table_Manager::get_table_name($post_type, 'main');

        // Build format array dynamically.
        $formats = [];
        foreach ($data as $key => $value) {
            if (in_array($key, ['post_author', 'post_parent', 'menu_order', 'comment_count'], true)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        // Update in database.
        $result = $wpdb->update(
            $table_name,
            $data,
            ['ID' => $post_id],
            $formats,
            ['%d']
        );

        if (false === $result) {
            Logger::error("Failed to update post ID {$post_id} in {$table_name}: " . $wpdb->last_error);
            return new \WP_Error('db_update_error', __('Could not update post in database.', 'slk-cpt-table-engine'));
        }

        // Clear cache.
        Cache::delete_post($post_id, $post_type);
        Cache::flush_post_type($post_type);

        Logger::info("Updated post ID {$post_id} in {$table_name}");

        return true;
    }

    /**
     * Delete a CPT entry.
     *
     * @param string $post_type The post type.
     * @param int    $post_id   The post ID.
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public static function delete(string $post_type, int $post_id)
    {
        global $wpdb;

        // Validate post ID.
        if (! Validator::is_valid_post_id($post_id)) {
            return new \WP_Error('invalid_post_id', __('Invalid post ID.', 'slk-cpt-table-engine'));
        }

        // Get table names.
        $main_table = Table_Manager::get_table_name($post_type, 'main');
        $meta_table = Table_Manager::get_table_name($post_type, 'meta');

        // Delete meta first.
        $wpdb->delete(
            $meta_table,
            ['post_id' => $post_id],
            ['%d']
        );

        // Delete post.
        $result = $wpdb->delete(
            $main_table,
            ['ID' => $post_id],
            ['%d']
        );

        if (false === $result) {
            Logger::error("Failed to delete post ID {$post_id} from {$main_table}: " . $wpdb->last_error);
            return new \WP_Error('db_delete_error', __('Could not delete post from database.', 'slk-cpt-table-engine'));
        }

        // Clear cache.
        Cache::delete_post($post_id, $post_type);
        Cache::flush_post_type($post_type);

        Logger::info("Deleted post ID {$post_id} from {$main_table}");

        return true;
    }

    /**
     * Get a single CPT entry.
     *
     * @param string $post_type The post type.
     * @param int    $post_id   The post ID.
     * @return object|null Post object or null if not found.
     */
    public static function get(string $post_type, int $post_id): ?object
    {
        global $wpdb;

        // Check cache first.
        $cached = Cache::get_post($post_id, $post_type);
        if (false !== $cached) {
            return $cached;
        }

        // Get table name.
        $table_name = Table_Manager::get_table_name($post_type, 'main');
        if (! $table_name) {
            return null;
        }

        // Query database.
        $post = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$table_name}` WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $post_id
            )
        );

        if (! $post) {
            return null;
        }

        // Cache the result.
        Cache::set_post($post_id, $post, $post_type);

        return $post;
    }

    /**
     * Query CPT entries.
     *
     * @param string $post_type The post type.
     * @param array  $args      Query arguments.
     * @return array Array of post objects.
     */
    public static function query(string $post_type, array $args = []): array
    {
        global $wpdb;

        // Get table name.
        $table_name = Table_Manager::get_table_name($post_type, 'main');
        if (! $table_name) {
            return [];
        }

        // Build query.
        $where = ['1=1'];
        $query_args = [];

        // Post status.
        if (isset($args['post_status'])) {
            $where[] = $wpdb->prepare('post_status = %s', $args['post_status']);
        }

        // Post author.
        if (isset($args['post_author'])) {
            $where[] = $wpdb->prepare('post_author = %d', $args['post_author']);
        }

        // Post parent.
        if (isset($args['post_parent'])) {
            $where[] = $wpdb->prepare('post_parent = %d', $args['post_parent']);
        }

        // Order by - Whitelist to prevent SQL injection.
        $orderby = $args['orderby'] ?? 'post_date';
        $allowed_orderby = ['ID', 'post_title', 'post_date', 'post_modified', 'post_author', 'post_name', 'menu_order', 'post_parent'];
        if (! in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'post_date';
        }

        // Order - Whitelist to prevent SQL injection.
        $order = strtoupper($args['order'] ?? 'DESC');
        if (! in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        // Limit and offset.
        $posts_per_page = isset($args['posts_per_page']) ? (int) $args['posts_per_page'] : 10;
        $offset = isset($args['offset']) ? (int) $args['offset'] : 0;

        // Build SQL.
        $where_clause = implode(' AND ', $where);
        $sql = "SELECT * FROM `{$table_name}` WHERE {$where_clause} ORDER BY {$orderby} {$order}";

        if ($posts_per_page > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $posts_per_page, $offset);
        }

        $posts = $wpdb->get_results($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return $posts ?: [];
    }

    /**
     * Get post count.
     *
     * @param string $post_type The post type.
     * @param array  $args      Query arguments.
     * @return int Post count.
     */
    public static function get_count(string $post_type, array $args = []): int
    {
        global $wpdb;

        // Get table name.
        $table_name = Table_Manager::get_table_name($post_type, 'main');
        if (! $table_name) {
            return 0;
        }

        // Build query.
        $where = ['1=1'];
        $query_args = [];

        // Post status.
        if (isset($args['post_status'])) {
            $where[] = $wpdb->prepare('post_status = %s', $args['post_status']);
        }

        // Build SQL.
        $where_clause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM `{$table_name}` WHERE {$where_clause}";

        $count = $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return (int) $count;
    }
}
